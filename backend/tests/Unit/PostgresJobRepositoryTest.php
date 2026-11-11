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
                'idempotency_key' => JobPosting::generateUuidV4()
            ],
            [
                'id' => 'd3b07384-d113-4956-a5d1-5c16454bc002',
                'title' => 'Test Title',
                'company' => 'Company 123',
                'status' => 'APPLIED',
                'salary_max' => 10000,
                'created_at' => '2026-10-22 15:30:00',
                'idempotency_key' => JobPosting::generateUuidV4()
            ]
        ];

        $mockData = $this->createMock(\PDOStatement::class);

        $bindValueCalls = 0;
        $mockData->expects($this->exactly(2))
            ->method('bindValue')
            ->willReturnCallback(function ($param, $value, $type) use (&$bindValueCalls) {
                $bindValueCalls++;
                if ($bindValueCalls === 1) {
                    $this->assertSame(':status', $param);
                    $this->assertSame('DRAFT', $value);
                    $this->assertSame(\PDO::PARAM_STR, $type);
                } elseif ($bindValueCalls === 2) {
                    $this->assertSame(':limit', $param);
                    $this->assertSame(2, $value);
                    $this->assertSame(\PDO::PARAM_INT, $type);
                }
                return true;
            });

        $mockData->expects($this->once())->method('execute');
        $mockData->expects($this->once())
            ->method('fetchAll')
            ->willReturn($jobArr);

        $pdoMock = $this->createMock(\PDO::class);

        $pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT id, title, company, status, salary_max, created_at, idempotency_key'))
            ->willReturn($mockData);

        $repository = new PostgresJobRepository($pdoMock);

        $result = $repository->findAll(JobStatus::DRAFT, 2);

        $hydratedJob = $result[0];
        $this->assertCount(2, $result);
        $this->assertInstanceOf(JobPosting::class, $hydratedJob);

        $this->assertSame(JobStatus::DRAFT, $hydratedJob->status);
        $this->assertInstanceOf(\DateTimeImmutable::class, $hydratedJob->createdAt);
    }

    public function testFindAllWithoutStatus()
    {
        $jobArr = [
            [
                'id' => 'd3b07384-d113-4956-a5d1-5c16454bc001',
                'title' => 'First Test Title',
                'company' => 'Company 1',
                'status' => 'DRAFT',
                'salary_max' => 4000,
                'created_at' => '2026-10-21 10:00:00',
                'idempotency_key' => JobPosting::generateUuidV4()
            ],
            [
                'id' => 'd3b07384-d113-4956-a5d1-5c16454bc002',
                'title' => 'Test Title',
                'company' => 'Company 123',
                'status' => 'APPLIED',
                'salary_max' => 10000,
                'created_at' => '2026-10-22 15:30:00',
                'idempotency_key' => JobPosting::generateUuidV4()
            ]
        ];

        $mockData = $this->createMock(\PDOStatement::class);

        $mockData->expects($this->exactly(1))
            ->method('bindValue')
            ->willReturnCallback(function ($param, $value, $type) {
                $this->assertSame(':limit', $param);
                $this->assertSame(2, $value);
                $this->assertSame(\PDO::PARAM_INT, $type);
                return true;
            });

        $mockData->expects($this->once())->method('execute');
        $mockData->expects($this->once())
            ->method('fetchAll')
            ->willReturn($jobArr);

        $pdoMock = $this->createMock(\PDO::class);

        $pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT id, title, company, status, salary_max, created_at, idempotency_key'))
            ->willReturn($mockData);

        $repository = new PostgresJobRepository($pdoMock);

        $result = $repository->findAll(null, 2);

        $hydratedJob = $result[0];
        $this->assertCount(2, $result);
        $this->assertInstanceOf(JobPosting::class, $hydratedJob);

        $this->assertSame(JobStatus::DRAFT, $hydratedJob->status);
        $this->assertInstanceOf(\DateTimeImmutable::class, $hydratedJob->createdAt);
    }

    public function testFindAllExceptionFailure()
    {
        $pdoMock = $this->createMock(\PDO::class);

        $pdoMock->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException());

        $repository = new PostgresJobRepository($pdoMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Could not return the jobs.");
        $repository->findAll(null, 2);
    }

    public function testSave()
    {
        $jobId = 'd3b07384-d113-4956-a5d1-5c16454bc002';
        $idempotencyKey = JobPosting::generateUuidV4();
        $job = [
            'id' => $jobId,
            'title' => 'Test Title',
            'company' => 'Company 123',
            'status' => JobStatus::APPLIED,
            'salaryMax' => 10000,
            'createdAt' => new \DateTimeImmutable('2026-10-22 15:30:00'),
            'idempotencyKey' => $idempotencyKey
        ];

        $jobPosting = new JobPosting(...$job);

        $spyBindings = [];
        $mockData = $this->createMock(\PDOStatement::class);

        $mockData->expects($this->exactly(7))
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

        $this->assertCount(7, $spyBindings, 'The six required parameters were not sent to bindValue.');

        $this->assertSame($jobId, $spyBindings[':id'] ?? null);
        $this->assertSame('Test Title', $spyBindings[':title'] ?? null);
        $this->assertSame('Company 123', $spyBindings[':company'] ?? null);
        $this->assertSame('APPLIED', $spyBindings[':status'] ?? null);
        $this->assertSame(10000, $spyBindings[':salary_max'] ?? null);
        $this->assertSame('2026-10-22 15:30:00+00:00', $spyBindings[':created_at'] ?? null);
        $this->assertSame($idempotencyKey, $spyBindings[':idempotency_key'] ?? null);
    }
}