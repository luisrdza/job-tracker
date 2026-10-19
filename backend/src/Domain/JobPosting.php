<?php
declare(strict_types=1);

namespace App\Domain;

readonly class JobPosting
{
    public function __construct(
        public string $id,
        public string $title,
        public string $company,
        public JobStatus $status,
        public int $salaryMax,
    ) {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('Title cannot be empty.');
        }

        if (trim($company) === '') {
            throw new \InvalidArgumentException('Company cannot be empty.');
        }

        if ($salaryMax <= 0) {
            throw new \InvalidArgumentException('Salary must be greater than zero.');
        }
    }

    public function canTransitionTo(JobStatus $newStatus): bool
    {
        return match (true) {
            $this->status === JobStatus::DRAFT && $newStatus === JobStatus::APPLIED => true,
            $this->status === JobStatus::APPLIED && ($newStatus === JobStatus::INTERVIEWING || $newStatus === JobStatus::REJECTED) => true,
            $this->status === JobStatus::INTERVIEWING && ($newStatus === JobStatus::OFFER || $newStatus === JobStatus::REJECTED) => true,
            default => false
        };
    }
}