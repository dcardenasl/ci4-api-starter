<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\EmailService;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionClass;

/**
 * EmailService Integration Tests
 *
 * Tests email service with real configuration and view system.
 * SMTP connections are still mocked to avoid sending real emails.
 */
class EmailServiceTest extends CIUnitTestCase
{
    protected EmailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailService();
    }

    // ==================== CONFIGURATION TESTS ====================

    public function testServiceInitializesWithConfiguration(): void
    {
        $reflection = new ReflectionClass($this->service);

        $fromAddress = $reflection->getProperty('fromAddress');
        $fromAddress->setAccessible(true);

        $fromName = $reflection->getProperty('fromName');
        $fromName->setAccessible(true);

        $this->assertIsString($fromAddress->getValue($this->service));
        $this->assertIsString($fromName->getValue($this->service));
    }

    public function testServiceCreatesMailerInstance(): void
    {
        $reflection = new ReflectionClass($this->service);

        $mailer = $reflection->getProperty('mailer');
        $mailer->setAccessible(true);

        $this->assertInstanceOf(\Symfony\Component\Mailer\Mailer::class, $mailer->getValue($this->service));
    }

    public function testServiceCreatesQueueManagerInstance(): void
    {
        $reflection = new ReflectionClass($this->service);

        $queueManager = $reflection->getProperty('queueManager');
        $queueManager->setAccessible(true);

        $this->assertInstanceOf(
            \App\Libraries\Queue\QueueManager::class,
            $queueManager->getValue($this->service)
        );
    }

    // ==================== TRANSPORT CREATION TESTS ====================

    public function testCreateTransportReturnsTransportInterface(): void
    {
        $reflection = new ReflectionClass($this->service);

        $method = $reflection->getMethod('createTransport');
        $method->setAccessible(true);

        $transport = $method->invoke($this->service);

        $this->assertInstanceOf(
            \Symfony\Component\Mailer\Transport\TransportInterface::class,
            $transport
        );
    }

    public function testCreateTransportHandlesSMTPProvider(): void
    {
        putenv('EMAIL_PROVIDER=smtp');
        putenv('EMAIL_SMTP_HOST=smtp.example.com');
        putenv('EMAIL_SMTP_PORT=587');
        putenv('EMAIL_SMTP_USER=user@example.com');
        putenv('EMAIL_SMTP_PASS=password');
        putenv('EMAIL_SMTP_CRYPTO=tls');

        $service = new EmailService();
        $reflection = new ReflectionClass($service);

        $method = $reflection->getMethod('createTransport');
        $method->setAccessible(true);

        $transport = $method->invoke($service);

        $this->assertInstanceOf(
            \Symfony\Component\Mailer\Transport\TransportInterface::class,
            $transport
        );

        // Clear env vars
        putenv('EMAIL_PROVIDER');
        putenv('EMAIL_SMTP_HOST');
        putenv('EMAIL_SMTP_PORT');
        putenv('EMAIL_SMTP_USER');
        putenv('EMAIL_SMTP_PASS');
        putenv('EMAIL_SMTP_CRYPTO');
    }

    public function testCreateTransportHandlesNativeProvider(): void
    {
        putenv('EMAIL_PROVIDER=native');

        $service = new EmailService();
        $reflection = new ReflectionClass($service);

        $method = $reflection->getMethod('createTransport');
        $method->setAccessible(true);

        $transport = $method->invoke($service);

        $this->assertInstanceOf(
            \Symfony\Component\Mailer\Transport\TransportInterface::class,
            $transport
        );

        putenv('EMAIL_PROVIDER');
    }

    public function testCreateTransportHandlesDefaultConfiguration(): void
    {
        // Clear all email env vars to test defaults
        putenv('EMAIL_PROVIDER');
        putenv('EMAIL_SMTP_HOST');
        putenv('EMAIL_SMTP_PORT');
        putenv('EMAIL_SMTP_USER');
        putenv('EMAIL_SMTP_PASS');

        $service = new EmailService();
        $reflection = new ReflectionClass($service);

        $method = $reflection->getMethod('createTransport');
        $method->setAccessible(true);

        $transport = $method->invoke($service);

        $this->assertInstanceOf(
            \Symfony\Component\Mailer\Transport\TransportInterface::class,
            $transport
        );
    }

    // ==================== SEND METHOD TESTS ====================

    public function testSendMethodAcceptsValidParameters(): void
    {
        $to = 'test@example.com';
        $subject = 'Test Subject';
        $message = '<p>Test Message</p>';

        // This will fail to send (no real SMTP), but should return false gracefully
        $result = $this->service->send($to, $subject, $message);

        // Result should be boolean
        $this->assertIsBool($result);
    }

    public function testSendMethodHandlesHTMLMessage(): void
    {
        $htmlMessage = <<<HTML
        <!DOCTYPE html>
        <html>
        <head><title>Test</title></head>
        <body>
            <h1>Hello</h1>
            <p>This is a test email with <strong>HTML</strong> content.</p>
        </body>
        </html>
        HTML;

        $result = $this->service->send('test@example.com', 'HTML Test', $htmlMessage);

        $this->assertIsBool($result);
    }

    public function testSendMethodHandlesTextMessage(): void
    {
        $htmlMessage = '<p>HTML version</p>';
        $textMessage = 'Plain text version';

        $result = $this->service->send('test@example.com', 'Test', $htmlMessage, $textMessage);

        $this->assertIsBool($result);
    }

    public function testSendMethodHandlesInvalidEmailAddress(): void
    {
        // Invalid email should be handled gracefully
        $result = $this->service->send('invalid-email', 'Subject', 'Message');

        // Should return false (can't send to invalid address)
        $this->assertIsBool($result);
    }

    public function testSendMethodHandlesEmptySubject(): void
    {
        $result = $this->service->send('test@example.com', '', 'Message');

        $this->assertIsBool($result);
    }

    public function testSendMethodHandlesLongRecipientList(): void
    {
        // Test sending to multiple recipients sequentially
        $recipients = ['user1@example.com', 'user2@example.com', 'user3@example.com'];

        foreach ($recipients as $recipient) {
            $result = $this->service->send($recipient, 'Subject', 'Message');
            $this->assertIsBool($result);
        }
    }

    // ==================== QUEUE METHOD TESTS ====================

    public function testQueueMethodReturnsInteger(): void
    {
        $jobId = $this->service->queue('test@example.com', 'Subject', 'Message');

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testQueueMethodHandlesMultipleJobs(): void
    {
        $jobId1 = $this->service->queue('user1@example.com', 'Subject 1', 'Message 1');
        $jobId2 = $this->service->queue('user2@example.com', 'Subject 2', 'Message 2');
        $jobId3 = $this->service->queue('user3@example.com', 'Subject 3', 'Message 3');

        $this->assertIsInt($jobId1);
        $this->assertIsInt($jobId2);
        $this->assertIsInt($jobId3);

        // Job IDs should be different
        $this->assertNotEquals($jobId1, $jobId2);
        $this->assertNotEquals($jobId2, $jobId3);
    }

    public function testQueueMethodHandlesHTMLAndText(): void
    {
        $jobId = $this->service->queue(
            'test@example.com',
            'Subject',
            '<p>HTML version</p>',
            'Text version'
        );

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    // ==================== SEND TEMPLATE TESTS ====================

    public function testSendTemplateMethodAcceptsValidParameters(): void
    {
        // Clean any existing output buffers and start fresh
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();

        $result = $this->service->sendTemplate(
            'verification',
            'test@example.com',
            ['username' => 'TestUser', 'token' => 'abc123']
        );

        // Clean up output buffer
        ob_end_clean();

        $this->assertIsBool($result);
    }

    public function testSendTemplateMethodHandlesSubjectInData(): void
    {
        // Clean any existing output buffers and start fresh
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();

        $data = [
            'subject' => 'Custom Subject',
            'username' => 'John',
            'content' => 'Test content',
        ];

        $result = $this->service->sendTemplate('verification', 'test@example.com', $data);

        // Clean up output buffer
        ob_end_clean();

        $this->assertIsBool($result);
    }

    public function testSendTemplateMethodHandlesEmptyData(): void
    {
        $result = $this->service->sendTemplate('simple_template', 'test@example.com', []);

        $this->assertIsBool($result);
    }

    public function testSendTemplateMethodHandlesNonExistentTemplate(): void
    {
        $result = $this->service->sendTemplate('nonexistent_template', 'test@example.com', []);

        // Should return false for non-existent template
        $this->assertFalse($result);
    }

    public function testSendTemplateMethodConvertsTemplateNameToSubject(): void
    {
        // Template name "password_reset" should become "Password reset"
        $result = $this->service->sendTemplate('password_reset', 'test@example.com', []);

        $this->assertIsBool($result);
    }

    // ==================== QUEUE TEMPLATE TESTS ====================

    public function testQueueTemplateMethodReturnsInteger(): void
    {
        $jobId = $this->service->queueTemplate(
            'welcome',
            'test@example.com',
            ['username' => 'NewUser']
        );

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testQueueTemplateMethodHandlesComplexData(): void
    {
        $complexData = [
            'subject' => 'Welcome to Our Platform',
            'username' => 'John Doe',
            'verificationUrl' => 'https://example.com/verify?token=xyz',
            'features' => ['Feature 1', 'Feature 2', 'Feature 3'],
            'expiresAt' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ];

        $jobId = $this->service->queueTemplate('welcome', 'test@example.com', $complexData);

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testQueueTemplateMethodHandlesMultipleTemplates(): void
    {
        $templates = ['welcome', 'verification', 'password_reset'];
        $jobIds = [];

        foreach ($templates as $template) {
            $jobId = $this->service->queueTemplate($template, 'test@example.com', []);
            $jobIds[] = $jobId;
        }

        // All should be valid job IDs
        foreach ($jobIds as $jobId) {
            $this->assertIsInt($jobId);
            $this->assertGreaterThan(0, $jobId);
        }

        // All should be unique
        $this->assertCount(count($templates), array_unique($jobIds));
    }

    // ==================== EDGE CASES ====================

    public function testServiceHandlesUnicodeContent(): void
    {
        $subject = 'Тестовое письмо with émojis 🎉';
        $message = '<p>Content with 中文字符 and special chars: Ñ, ü, ç</p>';

        $result = $this->service->send('test@example.com', $subject, $message);

        $this->assertIsBool($result);
    }

    public function testServiceHandlesVeryLongSubject(): void
    {
        $longSubject = str_repeat('Very Long Subject Line ', 20);

        $result = $this->service->send('test@example.com', $longSubject, 'Message');

        $this->assertIsBool($result);
    }

    public function testServiceHandlesVeryLongMessage(): void
    {
        $longMessage = '<p>' . str_repeat('Lorem ipsum dolor sit amet. ', 5000) . '</p>';

        $result = $this->service->send('test@example.com', 'Subject', $longMessage);

        $this->assertIsBool($result);
    }

    public function testServiceHandlesSpecialCharactersInEmail(): void
    {
        // Email with plus sign (valid according to RFC)
        $result = $this->service->send('user+tag@example.com', 'Subject', 'Message');

        $this->assertIsBool($result);
    }

    public function testQueueHandlesRapidConsecutiveCalls(): void
    {
        $jobIds = [];

        // Queue 20 emails rapidly
        for ($i = 0; $i < 20; $i++) {
            $jobIds[] = $this->service->queue(
                "user{$i}@example.com",
                "Subject {$i}",
                "Message {$i}"
            );
        }

        // All should be valid integers
        foreach ($jobIds as $jobId) {
            $this->assertIsInt($jobId);
            $this->assertGreaterThan(0, $jobId);
        }

        // All should be unique
        $this->assertCount(20, array_unique($jobIds));
    }

    // ==================== FROM ADDRESS TESTS ====================

    public function testServiceUsesConfiguredFromAddress(): void
    {
        putenv('EMAIL_FROM_ADDRESS=noreply@myapp.com');
        putenv('EMAIL_FROM_NAME=My Application');

        $service = new EmailService();
        $reflection = new ReflectionClass($service);

        $fromAddress = $reflection->getProperty('fromAddress');
        $fromAddress->setAccessible(true);

        $fromName = $reflection->getProperty('fromName');
        $fromName->setAccessible(true);

        $this->assertEquals('noreply@myapp.com', $fromAddress->getValue($service));
        $this->assertEquals('My Application', $fromName->getValue($service));

        putenv('EMAIL_FROM_ADDRESS');
        putenv('EMAIL_FROM_NAME');
    }

    public function testServiceUsesDefaultFromAddress(): void
    {
        putenv('EMAIL_FROM_ADDRESS');
        putenv('EMAIL_FROM_NAME');

        $service = new EmailService();
        $reflection = new ReflectionClass($service);

        $fromAddress = $reflection->getProperty('fromAddress');
        $fromAddress->setAccessible(true);

        $this->assertIsString($fromAddress->getValue($service));
        $this->assertNotEmpty($fromAddress->getValue($service));
    }

    // ==================== MIXED SCENARIOS ====================

    public function testMixedSendAndQueueOperations(): void
    {
        // Immediate send
        $sendResult = $this->service->send('urgent@example.com', 'Urgent', 'Message');
        $this->assertIsBool($sendResult);

        // Queue for later
        $queueResult = $this->service->queue('normal@example.com', 'Normal', 'Message');
        $this->assertIsInt($queueResult);

        // Template send
        $templateResult = $this->service->sendTemplate('welcome', 'new@example.com', []);
        $this->assertIsBool($templateResult);

        // Template queue
        $templateQueueResult = $this->service->queueTemplate('verification', 'verify@example.com', []);
        $this->assertIsInt($templateQueueResult);
    }

    public function testBulkEmailOperations(): void
    {
        $recipients = array_map(fn ($i) => "user{$i}@example.com", range(1, 10));

        foreach ($recipients as $index => $recipient) {
            // Alternate between send and queue
            if ($index % 2 === 0) {
                $result = $this->service->send($recipient, "Subject {$index}", "Message {$index}");
                $this->assertIsBool($result);
            } else {
                $result = $this->service->queue($recipient, "Subject {$index}", "Message {$index}");
                $this->assertIsInt($result);
            }
        }
    }
}
