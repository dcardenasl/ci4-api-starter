<?php

namespace App\Libraries\Queue\Jobs;

use App\Libraries\Queue\Job;
use App\Services\System\EmailService;

class SendTemplateEmailJob extends Job
{
    /**
     * Handle the job
     *
     * @return void
     */
    public function handle(): void
    {
        $template = $this->data['template'] ?? '';
        $to = $this->data['to'] ?? '';
        $templateData = $this->data['data'] ?? null;

        if (empty($template) || empty($to) || $templateData === null) {
            throw new \InvalidArgumentException('Missing required email data');
        }

        $emailService = new EmailService();
        $success = $emailService->sendTemplate($template, $to, $templateData);

        if (! $success) {
            throw new \RuntimeException("Failed to send template email '{$template}' to {$to}");
        }
    }
}
