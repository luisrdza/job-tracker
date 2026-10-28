<?php
declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\JobRepositoryInterface;
use App\Domain\JobPosting;
use App\Domain\JobStatus;

class PostgresJobRepository implements JobRepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo
    ) {
    }

    public function findAll(): array
    {
        try {
            $sql = "SELECT id, title, company, status, salary_max, created_at FROM jobs ORDER BY created_at DESC";
            $query = $this->pdo->prepare($sql);
            $query->execute();
            $result = $query->fetchAll();

            $jobs = array_map(function ($job) {
                return new JobPosting(
                    $job['id'],
                    $job['title'],
                    $job['company'],
                    JobStatus::from($job['status']),
                    $job['salary_max'],
                    new \DateTimeImmutable($job['created_at'])
                );
            }, $result);

            return $jobs;
        } catch (\Throwable $e) {
            error_log('Method PostgresJobRepository failed: ' . $e->getMessage());
            throw new \RuntimeException("Could not return the jobs. ", 0, $e);
        }
    }

    public function save(JobPosting $job): void
    {
        try {
            $sql = "INSERT INTO jobs (id, title, company, status, salary_max, created_at) VALUES (:id, :title, :company, :status, :salary_max, :created_at)";
            $query = $this->pdo->prepare($sql);

            $query->bindValue(':id', $job->id);
            $query->bindValue(':title', $job->title);
            $query->bindValue(':company', $job->company);
            $query->bindValue(':status', $job->status->value);
            $query->bindValue(':salary_max', $job->salaryMax, $job->salaryMax === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $query->bindValue(':created_at', $job->createdAt->format('Y-m-d H:i:sP'));

            $query->execute();
        } catch (\Throwable $e) {
            error_log('Error saving the job. Error message: ' . $e->getMessage());
            throw new \RuntimeException("The job was not stored. ", 0, $e);
        }
    }
}