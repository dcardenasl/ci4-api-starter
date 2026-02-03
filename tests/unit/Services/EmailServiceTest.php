<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\EmailService;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionClass;

/**
 * EmailService Unit Tests
 *
 * Tests email service configuration and queue functionality.
 * Note: Symfony Mailer is final and cannot be mocked, so send tests are in integration tests.
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

    public function testServiceInitializesWithFromAddress(): void
    {
        $reflection = new ReflectionClass($this->service);

        $fromAddress = $reflection->getProperty('fromAddress');
        $fromAddress->setAccessible(true);

        $value = $fromAddress->getValue($this->service);

        $this->assertIsString($value);
        $this->assertNotEmpty($value);
    }

    public function testServiceInitializesWithFromName(): void
    {
        $reflection = new ReflectionClass($this->service);

        $fromName = $reflection->getProperty('fromName');
        $fromName->setAccessible(true);

        $value = $fromName->getValue($this->service);

        $this->assertIsString($value);
        $this->assertNotEmpty($value);
    }

    public function testServiceUsesEnvironmentFromAddress(): void
    {
        putenv('EMAIL_FROM_ADDRESS=test@example.com');
        putenv('EMAIL_FROM_NAME=Test Application');

        $service = new EmailService();
        $reflection = new ReflectionClass($service);

        $fromAddress = $reflection->getProperty('fromAddress');
        $fromAddress->setAccessible(true);

        $fromName = $reflection->getProperty('fromName');
        $fromName->setAccessible(true);

        $this->assertEquals('test@example.com', $fromAddress->getValue($service));
        $this->assertEquals('Test Application', $fromName->getValue($service));

        putenv('EMAIL_FROM_ADDRESS');
        putenv('EMAIL_FROM_NAME');
    }

    public function testServiceUsesDefaultsWhenEnvNotSet(): void
    {
        putenv('EMAIL_FROM_ADDRESS');
        putenv('EMAIL_FROM_NAME');

        $service = new EmailService();
        $reflection = new ReflectionClass($service);

        $fromAddress = $reflection->getProperty('fromAddress');
        $fromAddress->setAccessible(true);

        $fromName = $reflection->getProperty('fromName');
        $fromName->setAccessible(true);

        $this->assertEquals('noreply@example.com', $fromAddress->getValue($service));
        $this->assertEquals('API Application', $fromName->getValue($service));
    }

    // ==================== TRANSPORT CREATION TESTS ====================

    public function testCreateTransportMethodExists(): void
    {
        $reflection = new ReflectionClass($this->service);

        $this->assertTrue($reflection->hasMethod('createTransport'));
    }

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

    public function testCreateTransportHandlesSMTPConfiguration(): void
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

        // Clean up
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

    public function testCreateTransportUsesDefaultProvider(): void
    {
        putenv('EMAIL_PROVIDER');

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

    // ==================== QUEUE FUNCTIONALITY TESTS ====================

    public function testQueueMethodReturnsInteger(): void
    {
        $jobId = $this->service->queue('test@example.com', 'Subject', 'Message');

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testQueueMethodHandlesNullTextMessage(): void
    {
        $jobId = $this->service->queue('test@example.com', 'Subject', 'Message', null);

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testQueueMethodHandlesTextMessage(): void
    {
        $jobId = $this->service->queue(
            'test@example.com',
            'Subject',
            '<p>HTML</p>',
            'Plain text'
        );

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testQueueTemplateMethodReturnsInteger(): void
    {
        $jobId = $this->service->queueTemplate('welcome', 'test@example.com', []);

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testQueueTemplateMethodHandlesData(): void
    {
        $data = [
            'username' => 'John Doe',
            'token' => 'abc123',
            'subject' => 'Welcome Email',
        ];

        $jobId = $this->service->queueTemplate('welcome', 'test@example.com', $data);

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testQueueTemplateMethodHandlesEmptyData(): void
    {
        $jobId = $this->service->queueTemplate('simple', 'test@example.com', []);

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testMultipleQueueCallsReturnDifferentJobIds(): void
    {
        $jobId1 = $this->service->queue('user1@example.com', 'Subject 1', 'Message 1');
        $jobId2 = $this->service->queue('user2@example.com', 'Subject 2', 'Message 2');
        $jobId3 = $this->service->queue('user3@example.com', 'Subject 3', 'Message 3');

        $this->assertNotEquals($jobId1, $jobId2);
        $this->assertNotEquals($jobId2, $jobId3);
        $this->assertNotEquals($jobId1, $jobId3);
    }

    // ==================== SEND METHOD BEHAVIOR TESTS ====================

    public function testSendMethodReturnsBoolean(): void
    {
        // This will fail to actually send (no real SMTP), but should return false gracefully
        $result = $this->service->send('test@example.com', 'Subject', 'Message');

        $this->assertIsBool($result);
    }

    public function testSendTemplateMethodReturnsBoolean(): void
    {
        // Should return false for non-existent template
        $result = $this->service->sendTemplate('nonexistent_template', 'test@example.com', []);

        $this->assertFalse($result);
    }

    // ==================== EDGE CASES ====================

    public function testQueueHandlesSpecialCharactersInEmail(): void
    {
        // Email with plus sign (valid according to RFC)
        $jobId = $this->service->queue('user+tag@example.com', 'Subject', 'Message');

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testQueueHandlesUnicodeInSubject(): void
    {
        $subject = 'Email with 中文 and émojis 🎉';

        $jobId = $this->service->queue('test@example.com', $subject, 'Message');

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testQueueHandlesLongMessage(): void
    {
        $longMessage = '<p>' . str_repeat('Lorem ipsum dolor sit amet. ', 1000) . '</p>';

        $jobId = $this->service->queue('test@example.com', 'Subject', $longMessage);

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }

    public function testQueueTemplateHandlesComplexData(): void
    {
        $complexData = [
            'subject' => 'Welcome',
            'username' => 'John Doe',
            'features' => ['Feature 1', 'Feature 2'],
            'nested' => ['key' => 'value'],
        ];

        $jobId = $this->service->queueTemplate('welcome', 'test@example.com', $complexData);

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
    }
}
