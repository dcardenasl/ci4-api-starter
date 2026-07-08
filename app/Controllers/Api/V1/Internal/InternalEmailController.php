<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Internal;

use App\DTO\Request\Internal\InternalEmailQueueRequestDTO;
use App\Interfaces\System\EmailServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Http\ApiController;

/**
 * Internal M2M endpoint for queuing emails.
 *
 * Called exclusively by trusted Domain apps via X-App-Key authentication
 * (see the `internal` route group + appKeyRequired filter). Delegates to
 * the Hub's EmailService::queue() so the Hub remains the single email
 * sender — no mailer configuration needed in Domain apps.
 *
 * This is a reference example of the "internal M2M endpoint" pattern: a
 * thin controller gated by appKeyRequired that proxies to an existing Hub
 * service. Follow the same shape (controller + optional RequestDTO +
 * route entry in internal.php) to add further internal endpoints.
 */
class InternalEmailController extends ApiController
{
    protected function resolveDefaultService(): object
    {
        return Services::emailService();
    }

    public function queue(): ResponseInterface
    {
        return $this->handleRequest(
            function (InternalEmailQueueRequestDTO $dto, SecurityContext $context): mixed {
                /** @var EmailServiceInterface $emailService */
                $emailService = Services::emailService();
                $jobId = $emailService->queue($dto->to, $dto->subject, $dto->message, $dto->text_message);
                return ['job_id' => $jobId];
            },
            InternalEmailQueueRequestDTO::class
        );
    }
}
