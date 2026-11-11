<?php
declare(strict_types=1);

namespace App\Domain;
interface JobRepositoryInterface
{
    public function save(JobPosting $job): void;
    public function findAll(?JobStatus $jobStatus, int $limit): array;
}