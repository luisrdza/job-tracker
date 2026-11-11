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

    public function findAll(?JobStatus $jobStatus, int $limit = 20, ?string $cursorDate = null, ?string $cursorId = null): array
    {
        try {
            $sql = "SELECT id, title, company, status, salary_max, created_at, idempotency_key 
                FROM jobs 
                WHERE 1=1 ";

            if ($jobStatus !== null) {
                $sql .= "AND status = :status ";
            }

            if ($cursorDate !== null && $cursorId !== null) {
                $sql .= "AND (created_at, id) < (:cursor_date, :cursor_id) ";
            }

            $sql .= "ORDER BY created_at DESC, id DESC
                    LIMIT :limit";

            $query = $this->pdo->prepare($sql);

            if ($jobStatus !== null) {
                $query->bindValue(':status', $jobStatus->value, \PDO::PARAM_STR);
            }

            if ($cursorDate !== null && $cursorId !== null) {
                $query->bindValue(':cursor_date', $cursorDate, \PDO::PARAM_STR);
                $query->bindValue(':cursor_id', $cursorId, \PDO::PARAM_STR);
            }
            $query->bindValue(':limit', (int) $limit, \PDO::PARAM_INT);
            $query->execute();
            $result = $query->fetchAll();

            $jobs = array_map(function ($job) {
                return new JobPosting(
                    $job['id'],
                    $job['title'],
                    $job['company'],
                    JobStatus::from($job['status']),
                    $job['salary_max'],
                    new \DateTimeImmutable($job['created_at']),
                    $job['idempotency_key']
                );
            }, $result);

            return $jobs;
        } catch (\Throwable $e) {
            error_log('Method PostgresJobRepository failed: ' . $e->getMessage());
            throw new \RuntimeException("Could not return the jobs.", 0, $e);
        }
    }

    public function save(JobPosting $job): void
    {
        try {
            $sql = "INSERT INTO jobs (id, title, company, status, salary_max, created_at, idempotency_key) VALUES (:id, :title, :company, :status, :salary_max, :created_at, :idempotency_key)";
            $query = $this->pdo->prepare($sql);

            $query->bindValue(':id', $job->id);
            $query->bindValue(':title', $job->title);
            $query->bindValue(':company', $job->company);
            $query->bindValue(':status', $job->status->value);
            $query->bindValue(':salary_max', $job->salaryMax, $job->salaryMax === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $query->bindValue(':created_at', $job->createdAt->format('Y-m-d H:i:sP'));
            $query->bindValue(':idempotency_key', $job->idempotencyKey);

            $query->execute();
        } catch (\Throwable $e) {
            error_log('Error saving the job. Error message: ' . $e->getMessage());
            if ((string) $e->getCode() === '23505') {
                throw new \RuntimeException("Idempotency key already exists.", 409, $e);
            }
            throw new \RuntimeException("The job was not stored.", 0, $e);
        }
    }
}