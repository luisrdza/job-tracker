<?php
declare(strict_types=1);

namespace App\Application\Console;
use App\Domain\JobPosting;
use App\Domain\JobStatus;
use App\Domain\JobRepositoryInterface;

class JobSeeder
{
    private const array SEED_JOBS = [
        [
            'id' => '11111111-1111-4111-8111-111111111111',
            'title' => 'Senior Backend PHP Engineer',
            'company' => 'Acme Cloud Systems',
            'status' => JobStatus::APPLIED,
            'salaryMax' => 100000,
            'createdAt' => '2026-10-22 15:30:00+00:00',
            'idempotencyKey' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1'
        ],
        [
            'id' => '22222222-2222-4222-8222-222222222222',
            'title' => 'Senior Frontend React Engineer',
            'company' => 'Globex Infrastructure',
            'status' => JobStatus::INTERVIEWING,
            'salaryMax' => 120000,
            'createdAt' => '2026-10-23 15:30:00+00:00',
            'idempotencyKey' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2'
        ],
        [
            'id' => '33333333-3333-4333-8333-333333333333',
            'title' => 'Full Stack Developer (React / Node)',
            'company' => 'Initech Digital',
            'status' => JobStatus::DRAFT,
            'salaryMax' => null,
            'createdAt' => '2026-10-24 15:30:00+00:00',
            'idempotencyKey' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3'
        ]
    ];
    public function __construct(
        private readonly JobRepositoryInterface $jobRepositoryInterface
    ) {
    }

    public function run(): int
    {
        $insertedCount = 0;

        foreach (self::SEED_JOBS as $data) {
            $data['createdAt'] = new \DateTimeImmutable($data['createdAt']);
            $job = new JobPosting(...$data);
            try {
                $this->jobRepositoryInterface->save($job);
                $insertedCount++;
            } catch (\RuntimeException $e) {
                if ($e->getCode() === 409) {
                    continue;
                }

                throw $e;
            }
        }

        return $insertedCount;
    }

    public function getTotalCount(): int
    {
        return count(self::SEED_JOBS);
    }
}