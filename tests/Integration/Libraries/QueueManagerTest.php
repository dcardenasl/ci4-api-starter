<?php

declare(strict_types=1);

namespace Tests\Integration\Libraries;

use App\Libraries\Queue\Job;
use App\Libraries\Queue\QueueManager;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

class QueueManagerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private ?string $previousRetryAfter = null;
    private ?string $previousMaxAttempts = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousRetryAfter = getenv('QUEUE_RETRY_AFTER') !== false ? (string) getenv('QUEUE_RETRY_AFTER') : null;
        $this->previousMaxAttempts = getenv('QUEUE_MAX_ATTEMPTS') !== false ? (string) getenv('QUEUE_MAX_ATTEMPTS') : null;

        putenv('QUEUE_RETRY_AFTER=0');
        putenv('QUEUE_MAX_ATTEMPTS=2');

        TestQueueSuccessJob::$handled = 0;
        TestQueueAlwaysFailJob::$failedCalls = 0;
    }

    protected function tearDown(): void
    {
        if ($this->previousRetryAfter === null) {
            putenv('QUEUE_RETRY_AFTER');
        } else {
            putenv('QUEUE_RETRY_AFTER=' . $this->previousRetryAfter);
        }

        if ($this->previousMaxAttempts === null) {
            putenv('QUEUE_MAX_ATTEMPTS');
        } else {
            putenv('QUEUE_MAX_ATTEMPTS=' . $this->previousMaxAttempts);
        }

        parent::tearDown();
    }

    public function testProcessRetriesAndMovesFailedJobAfterMaxAttempts(): void
    {
        $queue = new TestableQueueManager();
        $db = Database::connect();

        $queue->push(TestQueueAlwaysFailJob::class, ['case' => 'max-attempts'], 'default');

        // 1st attempt: should stay in jobs for retry.
        $queue->process('default');

        $jobAfterFirstAttempt = $db->table('jobs')->where('queue', 'default')->get()->getRow();
        $this->assertNotNull($jobAfterFirstAttempt);
        $this->assertSame(1, (int) $jobAfterFirstAttempt->attempts);
        $this->assertNull($jobAfterFirstAttempt->reserved_at);

        // 2nd attempt: reaches max attempts, should be moved to failed_jobs.
        $queue->process('default');

        $remainingJobs = $db->table('jobs')->where('queue', 'default')->countAllResults();
        $failedJobs = $db->table('failed_jobs')->where('queue', 'default')->countAllResults();

        $this->assertSame(0, $remainingJobs);
        $this->assertSame(1, $failedJobs);
        $this->assertSame(1, TestQueueAlwaysFailJob::$failedCalls);
    }

    public function testGetNextJobReclaimsStaleReservedJobs(): void
    {
        $queue = new TestableQueueManager();
        $db = Database::connect();

        $db->table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['job' => TestQueueSuccessJob::class, 'data' => []]),
            'attempts' => 0,
            'reserved_at' => time() - 120, // stale reservation
            'available_at' => time() - 120,
            'created_at' => time() - 120,
        ]);

        $reserved = $queue->reserveNextPublic('default');

        $this->assertNotNull($reserved);
        $this->assertSame('default', $reserved->queue);
    }

    public function testProcessExecutesQueuedJobSuccessfully(): void
    {
        $queue = new TestableQueueManager();
        $db = Database::connect();

        $queue->push(TestQueueSuccessJob::class, ['case' => 'success'], 'default');
        $queue->process('default');

        $remainingJobs = $db->table('jobs')->where('queue', 'default')->countAllResults();

        $this->assertSame(0, $remainingJobs);
        $this->assertSame(1, TestQueueSuccessJob::$handled);
    }
}

class TestableQueueManager extends QueueManager
{
    public function reserveNextPublic(string $queue = 'default'): ?object
    {
        return $this->getNextJob($queue);
    }
}

class TestQueueSuccessJob extends Job
{
    public static int $handled = 0;

    public function handle(): void
    {
        self::$handled++;
    }
}

class TestQueueAlwaysFailJob extends Job
{
    public static int $failedCalls = 0;

    public function handle(): void
    {
        throw new \RuntimeException('forced test failure');
    }

    public function failed(\Throwable $exception): void
    {
        self::$failedCalls++;
    }
}
