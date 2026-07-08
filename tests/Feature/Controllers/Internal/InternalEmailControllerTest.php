<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Internal;

use Config\Services;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * InternalEmailController Feature Tests
 *
 * Covers POST /api/v1/internal/email/queue: X-App-Key auth (missing,
 * invalid), request validation, and the happy path delegating to
 * EmailService::queue().
 */
class InternalEmailControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testQueueMissingAppKeyReturns401(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/internal/email/queue', [
                'to'      => 'user@example.com',
                'subject' => 'Hello',
                'message' => '<p>Hi</p>',
            ]);

        $result->assertStatus(401);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    public function testQueueInvalidAppKeyReturns403(): void
    {
        $result = $this->withHeaders(['X-App-Key' => 'apk_doesnotexist0000000000000000000'])
            ->withBodyFormat('json')
            ->post('/api/v1/internal/email/queue', [
                'to'      => 'user@example.com',
                'subject' => 'Hello',
                'message' => '<p>Hi</p>',
            ]);

        $result->assertStatus(403);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    public function testQueueHappyPathReturnsJobId(): void
    {
        $rawKey = $this->createActiveApiKey();

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/internal/email/queue', [
                'to'           => 'user@example.com',
                'subject'      => 'Hello',
                'message'      => '<p>Hi</p>',
                'text_message' => 'Hi',
            ]);

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);

        $this->assertSame('success', $json['status']);
        $this->assertArrayHasKey('job_id', $json['data']);
        $this->assertGreaterThan(0, $json['data']['job_id']);

        $job = \Config\Database::connect()
            ->table('jobs')
            ->where('id', $json['data']['job_id'])
            ->get()
            ->getRowArray();

        $this->assertNotNull($job, 'Queued email must create a row in the jobs table');
        $this->assertSame('emails', $job['queue']);

        $payload = json_decode((string) $job['payload'], true);
        $this->assertSame(\App\Libraries\Queue\Jobs\SendEmailJob::class, $payload['job']);
        $this->assertSame('user@example.com', $payload['data']['to']);
        $this->assertSame('Hello', $payload['data']['subject']);
    }

    public function testQueueMissingRequiredFieldsReturns422(): void
    {
        $rawKey = $this->createActiveApiKey();

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/internal/email/queue', [
                'subject' => 'Hello',
            ]);

        $result->assertStatus(422);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    public function testQueueInvalidEmailReturns422(): void
    {
        $rawKey = $this->createActiveApiKey();

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/internal/email/queue', [
                'to'      => 'not-an-email',
                'subject' => 'Hello',
                'message' => '<p>Hi</p>',
            ]);

        $result->assertStatus(422);
    }

    /**
     * Insert an API key directly and return the raw key value.
     */
    private function createActiveApiKey(string $name = 'internal-email-test-key', bool $isActive = true): string
    {
        $material = Services::apiKeyMaterialService();
        $rawKey   = $material->generateRawKey();

        \Config\Database::connect()
            ->table('api_keys')
            ->insert([
                'name'                => $name,
                'key_prefix'          => substr($rawKey, 0, 8),
                'key_hash'            => $material->hash($rawKey),
                'is_active'           => $isActive ? 1 : 0,
                'rate_limit_requests' => 600,
                'rate_limit_window'   => 60,
                'user_rate_limit'     => 60,
                'ip_rate_limit'       => 200,
                'created_at'          => date('Y-m-d H:i:s'),
            ]);

        return $rawKey;
    }
}
