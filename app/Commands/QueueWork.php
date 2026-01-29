<?php

namespace App\Commands;

use App\Libraries\Queue\QueueManager;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class QueueWork extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Queue';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'queue:work';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Process jobs from the queue';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'queue:work [options]';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '--queue' => 'The queue to process (default: default)',
        '--once' => 'Process a single job and exit',
        '--sleep' => 'Seconds to sleep between iterations (default: 3)',
        '--max-jobs' => 'Maximum number of jobs to process (0 = unlimited)',
    ];

    /**
     * Run the command
     *
     * @param array<int, string> $params
     * @return void
     */
    public function run(array $params)
    {
        $queue = CLI::getOption('queue') ?? 'default';
        $once = CLI::getOption('once') !== null;
        $sleep = (int) (CLI::getOption('sleep') ?? 3);
        $maxJobs = (int) (CLI::getOption('max-jobs') ?? 0);

        $queueManager = new QueueManager();
        $processedJobs = 0;

        CLI::write("Queue worker started for queue: {$queue}", 'green');

        if ($once) {
            CLI::write('Processing single job...', 'yellow');
            $queueManager->process($queue);
            CLI::write('Job processed', 'green');
            return;
        }

        // Continuous processing
        while (true) {
            try {
                // Get stats before processing
                $stats = $queueManager->getStats($queue);

                if ($stats['pending'] > 0) {
                    CLI::write(sprintf(
                        '[%s] Processing job... (Pending: %d, Processing: %d, Failed: %d)',
                        date('Y-m-d H:i:s'),
                        $stats['pending'],
                        $stats['processing'],
                        $stats['failed']
                    ), 'yellow');

                    $queueManager->process($queue);
                    $processedJobs++;

                    CLI::write(sprintf(
                        '[%s] Job completed (Total processed: %d)',
                        date('Y-m-d H:i:s'),
                        $processedJobs
                    ), 'green');

                    // Check if we've reached max jobs
                    if ($maxJobs > 0 && $processedJobs >= $maxJobs) {
                        CLI::write("Reached maximum jobs limit ({$maxJobs}). Exiting...", 'cyan');
                        break;
                    }
                } else {
                    // No jobs, sleep
                    if ($processedJobs > 0) {
                        CLI::write(sprintf(
                            '[%s] No pending jobs. Waiting %d seconds...',
                            date('Y-m-d H:i:s'),
                            $sleep
                        ), 'cyan');
                    }

                    sleep($sleep);
                }
            } catch (\Throwable $e) {
                CLI::error(sprintf(
                    '[%s] Error: %s',
                    date('Y-m-d H:i:s'),
                    $e->getMessage()
                ));

                // Log error
                log_message('error', 'Queue worker error: ' . $e->getMessage());

                // Continue processing after error
                sleep($sleep);
            }
        }

        CLI::write('Queue worker stopped', 'green');
    }
}
