<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Domain\JobPosting;
use App\Domain\JobStatus;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JobPosting::class)]
class JobPostingTest extends TestCase
{
    public function testJobPostingValidArguments(): void
    {
        $jobArr = [
            'id' => 'V25',
            'title' => 'Test Title',
            'company' => 'Company 123',
            'status' => JobStatus::APPLIED,
            'salaryMax' => 10000
        ];
        $jobPosting = new JobPosting(...$jobArr);

        $this->assertIsObject($jobPosting);
        $this->assertSame('Test Title', $jobPosting->title);
        $this->assertSame('Company 123', $jobPosting->company);
    }

    public function testJobPostingInvalidArguments(): void
    {
        $jobArr = [
            'id' => 'V25',
            'title' => '',
            'company' => '  ',
            'status' => JobStatus::APPLIED,
            'salaryMax' => 0
        ];
        $this->expectException(InvalidArgumentException::class);
        new JobPosting(...$jobArr);
    }

    public function testJobPostingCanTransitionTo(): void
    {
        $jobArr = [
            'id' => 'V25',
            'title' => 'Test Title',
            'company' => 'Company 123',
            'status' => JobStatus::DRAFT,
            'salaryMax' => 10000
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