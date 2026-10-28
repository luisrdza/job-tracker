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
        public ?int $salaryMax,
        public \DateTimeInterface $createdAt,
    ) {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('Title cannot be empty.');
        }

        if (trim($company) === '') {
            throw new \InvalidArgumentException('Company cannot be empty.');
        }

        if ($salaryMax !== null && $salaryMax <= 0) {
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

    public static function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versión 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variante RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function toArray(): array
    {
        return [
            "id" => $this->id,
            "title" => $this->title,
            "company" => $this->company,
            "status" => $this->status->value,
            "salary_max" => $this->salaryMax,
            "created_at" => $this->createdAt->format(\DateTimeInterface::ATOM)
        ];
    }
}