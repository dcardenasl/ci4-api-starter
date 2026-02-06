<?php

namespace App\Libraries\Queue;

use Throwable;

abstract class Job
{
    /**
     * Job data
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Number of attempts
     *
     * @var int
     */
    protected int $attempts = 0;

    /**
     * Job ID in the queue
     *
     * @var int|null
     */
    protected ?int $jobId = null;

    /**
     * Create a new job instance
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Handle the job
     * Must be implemented by child classes
     *
     * @return void
     */
    abstract public function handle(): void;

    /**
     * Handle a job failure
     * Can be overridden by child classes
     *
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        log_message('error', 'Job failed: ' . get_class($this) . ' - ' . $exception->getMessage());
    }

    /**
     * Get the number of attempts
     *
     * @return int
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * Set the number of attempts
     *
     * @param int $attempts
     * @return self
     */
    public function setAttempts(int $attempts): self
    {
        $this->attempts = $attempts;
        return $this;
    }

    /**
     * Get the job ID
     *
     * @return int|null
     */
    public function getJobId(): ?int
    {
        return $this->jobId;
    }

    /**
     * Set the job ID
     *
     * @param int $jobId
     * @return self
     */
    public function setJobId(int $jobId): self
    {
        $this->jobId = $jobId;
        return $this;
    }

    /**
     * Get job data
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
