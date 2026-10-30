<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Domain\JobPosting;
use App\Domain\JobStatus;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JobPosting::class)]
class JobPostingTest extends TestCase
{
    public static function invalidJobProvider(): iterable
    {
        $uuid = JobPosting::generateUuidV4();
        return [
            'empty title' => [
                'V25',
                '',
                'Company 123',
                JobStatus::APPLIED,
                10,
                $uuid
            ],
            'title only spaces' => [
                'V25',
                '   ',
                'Company 123',
                JobStatus::APPLIED,
                10,
                $uuid
            ],
            'empty company' => [
                'V25',
                'Title 123',
                '',
                JobStatus::APPLIED,
                10,
                $uuid
            ],
            'salary equals to zero' => [
                'V25',
                'Title 123',
                'Company 123',
                JobStatus::APPLIED,
                0,
                $uuid
            ],
            'negative salary' => [
                'V25',
                'Title 123',
                'Company 123',
                JobStatus::APPLIED,
                -10,
                $uuid
            ]
        ];
    }

    public function testJobPostingValidArguments(): void
    {
        $jobArr = [
            'id' => 'V25',
            'title' => 'Test Title',
            'company' => 'Company 123',
            'status' => JobStatus::APPLIED,
            'salaryMax' => 10000,
            'createdAt' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            'idempotencyKey' => JobPosting::generateUuidV4()
        ];
        $jobPosting = new JobPosting(...$jobArr);

        $this->assertIsObject($jobPosting);
        $this->assertSame('Test Title', $jobPosting->title);
        $this->assertSame('Company 123', $jobPosting->company);
    }

    public function testJobPostingNullSalaryMax(): void
    {
        $jobArr = [
            'id' => 'V25',
            'title' => 'Test Title',
            'company' => 'Company 123',
            'status' => JobStatus::APPLIED,
            'salaryMax' => null,
            'createdAt' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            'idempotencyKey' => JobPosting::generateUuidV4()
        ];
        $jobPosting = new JobPosting(...$jobArr);

        $this->assertIsObject($jobPosting);
        $this->assertSame(null, $jobPosting->salaryMax);
    }

    #[DataProvider('invalidJobProvider')]
    public function testJobPostingInvalidArguments(string $id, string $title, string $company, JobStatus $status, int|null $salaryMax, string $idempotencyKey): void
    {
        $createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->expectException(InvalidArgumentException::class);
        new JobPosting(
            $id,
            $title,
            $company,
            $status,
            $salaryMax,
            $createdAt,
            $idempotencyKey
        );
    }

    public function testJobPostingCanTransitionTo(): void
    {
        $jobArr = [
            'id' => 'V25',
            'title' => 'Test Title',
            'company' => 'Company 123',
            'status' => JobStatus::DRAFT,
            'salaryMax' => 10000,
            'createdAt' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            'idempotencyKey' => JobPosting::generateUuidV4()
        ];
        $firstJobPosting = new JobPosting(...$jobArr);
        $this->assertTrue($firstJobPosting->canTransitionTo(JobStatus::APPLIED));
        $this->assertFalse($firstJobPosting->canTransitionTo(JobStatus::INTERVIEWING));
        $this->assertFalse($firstJobPosting->canTransitionTo(JobStatus::REJECTED));
        $this->assertFalse($firstJobPosting->canTransitionTo(JobStatus::OFFER));

        $jobArr['status'] = JobStatus::APPLIED;
        $secondJobPosting = new JobPosting(...$jobArr);
        $this->assertTrue($secondJobPosting->canTransitionTo(JobStatus::INTERVIEWING));
        $this->assertTrue($secondJobPosting->canTransitionTo(JobStatus::REJECTED));
        $this->assertFalse($secondJobPosting->canTransitionTo(JobStatus::DRAFT));
        $this->assertFalse($secondJobPosting->canTransitionTo(JobStatus::OFFER));

        $jobArr['status'] = JobStatus::INTERVIEWING;
        $thirdJobPosting = new JobPosting(...$jobArr);
        $this->assertTrue($thirdJobPosting->canTransitionTo(JobStatus::OFFER));
        $this->assertTrue($thirdJobPosting->canTransitionTo(JobStatus::REJECTED));
        $this->assertFalse($thirdJobPosting->canTransitionTo(JobStatus::DRAFT));
        $this->assertFalse($thirdJobPosting->canTransitionTo(JobStatus::APPLIED));

        $jobArr['status'] = JobStatus::OFFER;
        $fourthJobPosting = new JobPosting(...$jobArr);
        $this->assertFalse($fourthJobPosting->canTransitionTo(JobStatus::INTERVIEWING));
        $this->assertFalse($fourthJobPosting->canTransitionTo(JobStatus::REJECTED));
        $this->assertFalse($fourthJobPosting->canTransitionTo(JobStatus::DRAFT));
        $this->assertFalse($fourthJobPosting->canTransitionTo(JobStatus::APPLIED));

        $jobArr['status'] = JobStatus::REJECTED;
        $fourthJobPosting = new JobPosting(...$jobArr);
        $this->assertFalse($fourthJobPosting->canTransitionTo(JobStatus::INTERVIEWING));
        $this->assertFalse($fourthJobPosting->canTransitionTo(JobStatus::OFFER));
        $this->assertFalse($fourthJobPosting->canTransitionTo(JobStatus::DRAFT));
        $this->assertFalse($fourthJobPosting->canTransitionTo(JobStatus::APPLIED));
    }
}