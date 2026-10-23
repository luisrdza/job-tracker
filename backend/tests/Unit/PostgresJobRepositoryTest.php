<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use App\Domain\JobStatus;
use App\Domain\JobPosting;
use App\Infrastructure\PostgresJobRepository;

#[CoversClass(PostgresJobRepository::class)]
class PostgresJobRepositoryTest extends TestCase
{
    public function testFindAll()
    {
        $jobArr = [
            [
                'id' => 'd3b07384-d113-4956-a5d1-5c16454bc001',
                'title' => 'First Test Title',
                'company' => 'Company 1',
                'status' => 'DRAFT',
                'salary_max' => 4000,
                'created_at' => '2026-10-21 10:00:00',
            ],
            [
                'id' => 'd3b07384-d113-4956-a5d1-5c16454bc002',
                'title' => 'Test Title',
                'company' => 'Company 123',
                'status' => 'APPLIED',
                'salary_max' => 10000,
                'created_at' => '2026-10-22 15:30:00'
            ]
        ];

        $mockData = $this->createMock(\PDOStatement::class);

        $mockData->expects($this->once())->method('execute');
        $mockData->expects($this->once())
            ->method('fetchAll')
            ->willReturn($jobArr);

        $pdoMock = $this->createMock(\PDO::class);

        $pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT id, title, company, status, salary_max, created_at FROM jobs'))
            ->willReturn($mockData);

        $repository = new PostgresJobRepository($pdoMock);

        $result = $repository->findAll();

        $hydratedJob = $result[0];
        $this->assertCount(2, $result);
        $this->assertInstanceOf(JobPosting::class, $hydratedJob);

        $this->assertSame(JobStatus::DRAFT, $hydratedJob->status);
        $this->assertInstanceOf(\DateTimeImmutable::class, $hydratedJob->createdAt);
    }

    public function testSave()
    {
        $jobId = 'd3b07384-d113-4956-a5d1-5c16454bc002';
        $job = [
            'id' => $jobId,
            'title' => 'Test Title',
            'company' => 'Company 123',
            'status' => JobStatus::APPLIED,
            'salaryMax' => 10000,
            'createdAt' => new \DateTimeImmutable('2026-10-22 15:30:00')
        ];

        $jobPosting = new JobPosting(...$job);

        $spyBindings = [];
        $mockData = $this->createMock(\PDOStatement::class);

        $mockData->expects($this->exactly(6))
            ->method('bindValue')
            ->willReturnCallback(function (string $param, mixed $value) use (&$spyBindings): bool {
                $spyBindings[$param] = $value;
                return true;
            });

        $mockData->expects($this->once())->method('execute');

        $pdoMock = $this->createMock(\PDO::class);

        $pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO jobs'))
            ->willReturn($mockData);

        $repository = new PostgresJobRepository($pdoMock);

        $repository->save($jobPosting);

        $this->assertCount(6, $spyBindings, 'The six required parameters were not sent to bindValue.');

        $this->assertSame($jobId, $spyBindings[':id'] ?? null);
        $this->assertSame('Test Title', $spyBindings[':title'] ?? null);
        $this->assertSame('Company 123', $spyBindings[':company'] ?? null);
        $this->assertSame('APPLIED', $spyBindings[':status'] ?? null);
        $this->assertSame(10000, $spyBindings[':salary_max'] ?? null);
        $this->assertSame('2026-10-22 15:30:00+00:00', $spyBindings[':created_at'] ?? null);
    }
}