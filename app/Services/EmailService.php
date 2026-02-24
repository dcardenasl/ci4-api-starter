<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\EmailServiceInterface;
use App\Libraries\Queue\Jobs\SendTemplateEmailJob;
use App\Libraries\Queue\QueueManager;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Throwable;

class EmailService implements EmailServiceInterface
{
    protected MailerInterface $mailer;
    protected QueueManager $queueManager;
    protected string $fromAddress;
    protected string $fromName;

    public function __construct(
        ?MailerInterface $mailer = null,
        ?QueueManager $queueManager = null
    ) {
        $this->fromAddress = env('EMAIL_FROM_ADDRESS', 'noreply@example.com');
        $this->fromName = env('EMAIL_FROM_NAME', 'API Application');

        // Use injected mailer or create default
        $this->mailer = $mailer ?? $this->createDefaultMailer();
        $this->queueManager = $queueManager ?? new QueueManager();
    }

    /**
     * Create default mailer from configuration
     *
     * @return Mailer
     */
    protected function createDefaultMailer(): Mailer
    {
        $transport = $this->createTransport();
        return new Mailer($transport);
    }

    /**
     * Create email transport based on configuration
     *
     * @return \Symfony\Component\Mailer\Transport\TransportInterface
     */
    protected function createTransport()
    {
        $provider = env('EMAIL_PROVIDER', 'smtp');

        if ($provider === 'smtp') {
            $host = env('EMAIL_SMTP_HOST', 'localhost');
            $port = env('EMAIL_SMTP_PORT', 25);
            $user = env('EMAIL_SMTP_USER', '');
            $pass = env('EMAIL_SMTP_PASS', '');
            $crypto = env('EMAIL_SMTP_CRYPTO', 'tls');

            // Build DSN for Symfony Mailer
            $dsn = sprintf(
                'smtp://%s:%s@%s:%s',
                urlencode($user),
                urlencode($pass),
                $host,
                $port
            );

            if ($crypto) {
                $dsn .= "?encryption={$crypto}";
            }

            return Transport::fromDsn($dsn);
        }

        // Fallback to native mail
        return Transport::fromDsn('native://default');
    }

    /**
     * Send an email immediately
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $message Email body (HTML)
     * @param string|null $textMessage Plain text version (optional)
     * @return bool
     */
    public function send(string $to, string $subject, string $message, ?string $textMessage = null): bool
    {
        try {
            $email = (new Email())
                ->from("{$this->fromName} <{$this->fromAddress}>")
                ->to($to)
                ->subject($subject)
                ->html($message);

            if ($textMessage) {
                $email->text($textMessage);
            }

            $this->mailer->send($email);

            log_message('info', "Email sent to {$to}: {$subject}");

            return true;
        } catch (Throwable $e) {
            log_message('error', "Failed to send email to {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue an email to be sent later
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $message Email body (HTML)
     * @param string|null $textMessage Plain text version (optional)
     * @return int Job ID
     */
    public function queue(string $to, string $subject, string $message, ?string $textMessage = null): int
    {
        return $this->queueManager->push(
            \App\Libraries\Queue\Jobs\SendEmailJob::class,
            [
                'to' => $to,
                'subject' => $subject,
                'message' => $message,
                'textMessage' => $textMessage,
            ],
            'emails'
        );
    }

    /**
     * Send an email using a template
     *
     * @param string $template Template name (e.g., 'verification')
     * @param string $to Recipient email address
     * @param array<string, mixed> $data Template data
     * @return bool
     */
    public function sendTemplate(string $template, string $to, array $data): bool
    {
        $request = service('request');
        $language = service('language');
        $previousLocale = $language->getLocale();
        $targetLocale = isset($data['locale']) && is_string($data['locale']) ? $data['locale'] : null;

        try {
            if ($targetLocale !== null && $targetLocale !== '') {
                $language->setLocale($targetLocale);
                if (method_exists($request, 'setLocale')) {
                    $request->setLocale($targetLocale);
                }
            }

            $viewPath = "emails/{$template}";
            $message = view($viewPath, $data);

            // Extract subject from data or use template name
            $subject = $data['subject'] ?? ucfirst(str_replace('_', ' ', $template));

            return $this->send($to, $subject, $message);
        } catch (Throwable $e) {
            log_message('error', "Failed to send template email: " . $e->getMessage());
            return false;
        } finally {
            $language->setLocale($previousLocale);
            if (method_exists($request, 'setLocale')) {
                $request->setLocale($previousLocale);
            }
        }
    }

    /**
     * Queue a template email
     *
     * @param string $template Template name
     * @param string $to Recipient email address
     * @param array<string, mixed> $data Template data
     * @return int Job ID
     */
    public function queueTemplate(string $template, string $to, array $data): int
    {
        if (! isset($data['locale']) || ! is_string($data['locale']) || $data['locale'] === '') {
            $language = service('language');
            $data['locale'] = method_exists(service('request'), 'getLocale')
                ? service('request')->getLocale()
                : $language->getLocale();
        }

        return $this->queueManager->push(
            SendTemplateEmailJob::class,
            [
                'template' => $template,
                'to' => $to,
                'data' => $data,
            ],
            'emails'
        );
    }
}
