<?php
declare(strict_types=1);

use App\Application\Console\JobSeeder;
use App\Domain\JobPosting;
use App\Domain\JobRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JobSeeder::class)]
class JobSeederTest extends TestCase
{
    public function testRunSuccess(): void
    {
        $repositoryInterfaceMock = $this->createMock(JobRepositoryInterface::class);
        $repositoryInterfaceMock->expects($this->exactly(3))
            ->method('save');

        $seeder = new JobSeeder($repositoryInterfaceMock);
        $result = $seeder->run();

        $this->assertSame(3, $result);
    }

    public function testRunIgnoresDuplicateKeyCollisions(): void
    {
        $repositoryInterfaceMock = $this->createMock(JobRepositoryInterface::class);

        $calls = 0;
        $repositoryInterfaceMock->expects($this->exactly(3))
            ->method('save')
            ->with($this->isInstanceOf(JobPosting::class))
            ->willReturnCallback(function (JobPosting $job) use (&$calls) {
                $calls++;
                if ($calls === 2) {
                    throw new \RuntimeException('Idempotency key already exists.', 409);
                }
            });

        $seeder = new JobSeeder($repositoryInterfaceMock);
        $result = $seeder->run();

        $this->assertSame(2, $result);
    }

    public function testRunRethrowsNonConflictExceptions(): void
    {
        $repositoryInterfaceMock = $this->createMock(JobRepositoryInterface::class);

        $repositoryInterfaceMock->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(JobPosting::class))
            ->willThrowException(new \RuntimeException('The job was not stored.', 0));

        $seeder = new JobSeeder($repositoryInterfaceMock);

        $caughtException = null;
        try {
            $seeder->run();
        } catch (\RuntimeException $e) {
            $caughtException = $e;
        }

        $this->assertNotNull($caughtException);
        $this->assertSame('The job was not stored.', $caughtException->getMessage());
        $this->assertSame(0, $caughtException->getCode());
    }
}