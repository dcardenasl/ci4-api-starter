<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\HTTP\ApiRequest;
use App\Libraries\ApiResponse;
use App\Libraries\ContextHolder;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

/**
 * Base API Controller
 *
 * Provides standardized request handling and automated DTO validation.
 *
 * @property \App\HTTP\ApiRequest $request
 */
abstract class ApiController extends Controller
{
    use ResponseTrait;

    protected string $serviceName = '';
    protected ?object $service = null;

    /**
     * Map controller actions to success HTTP status codes.
     * @var array<string, int>
     */
    protected array $statusCodes = [
        'store'   => 201,
        'upload'  => 201,
        'destroy' => 200,
        'delete'  => 200,
    ];

    /**
     * Get the service instance associated with this controller
     */
    protected function getService(): object
    {
        if ($this->service === null) {
            $method = $this->serviceName;
            $this->service = \Config\Services::$method();
        }
        return $this->service;
    }

    /**
     * Core request handler with automated DTO support
     *
     * @param string|callable $target Service method name or custom callable
     * @param string|null $dtoClass Optional DTO class to validate and map request data
     * @param array|null $additionalParams Extra params to merge into the request
     */
    protected function handleRequest(string|callable $target, ?string $dtoClass = null, ?array $additionalParams = null): ResponseInterface
    {
        try {
            $data = (array) $this->collectRequestData($additionalParams);
            $securityContext = $this->establishSecurityContext();

            $result = $this->executeTarget($target, $dtoClass, $data, $securityContext);
            // If result is already a response, return it directly
            if ($result instanceof ResponseInterface) {
                return $result;
            }

            $methodName = is_string($target) ? $target : '';
            $resultObject = ApiResponse::fromResult($result, $methodName, $this->statusCodes);

            return $this->respond($resultObject->body, $resultObject->status);
        } catch (ValidationException $e) {
            return $this->respond($e->toArray(), 422);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Establish the security context
     */
    private function establishSecurityContext(): \App\DTO\SecurityContext
    {
        // If a context is already established (e.g., by a Filter or a Test), respect it
        $existingContext = ContextHolder::get();
        if ($existingContext !== null && $existingContext->userId !== null) {
            return $existingContext;
        }

        $securityContext = new \App\DTO\SecurityContext($this->getUserId(), $this->getUserRole());

        // Set context globally for this request
        ContextHolder::set($securityContext);

        return $securityContext;
    }
    /**
     * Execute the target service method or callable
     */
    private function executeTarget(string|callable $target, ?string $dtoClass, array $data, \App\DTO\SecurityContext $context): mixed
    {
        // 1. Resolve Payload (DTO or Array)
        $payload = $data;
        if ($dtoClass !== null) {
            if (!class_exists($dtoClass)) {
                throw new \InvalidArgumentException("DTO class '{$dtoClass}' not found.");
            }
            $payload = new $dtoClass($data);
        }

        // 2. Resolve and execute target
        // If string, assume it's a method of the associated service
        if (is_string($target)) {
            return $this->getService()->{$target}($payload, $context);
        }

        // Otherwise, execute as a standard callable (Closure, [$this, 'method'], etc.)
        return $target($payload, $context);
    }

    /**
     * Standardize response output
     */
    public function respond(mixed $data = null, ?int $status = null, string $message = ''): ResponseInterface
    {
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        if ($data !== null) {
            $data = ApiResponse::convertDataToArrays($data);
        }

        // Determine status if not provided (fallback)
        if ($status === null) {
            $status = (isset($data['status']) && $data['status'] === 'error') ? 400 : 200;
            if (isset($data['code']) && is_int($data['code'])) {
                $status = $data['code'];
            }
        }

        return $this->response->setJSON($data)->setStatusCode($status);
    }


    /**
     * Collect all incoming request data and inject auth context
     */
    protected function collectRequestData(?array $params = null): array
    {
        /** @var \App\HTTP\ApiRequest $request */
        $request = $this->request;

        // Default precedence: Params > JSON > Post > Raw > Get.
        // For GET requests, query params must win to avoid stale body payload from prior requests in tests.
        $data = array_merge(
            (array) $request->getGet(),
            (array) $request->getRawInput(),
            (array) $request->getPost(),
            (array) $request->getJSON(true),
            $request->getFiles()
        );

        if (strtoupper($request->getMethod()) === 'GET') {
            $data = array_merge($data, (array) $request->getGet());
        }

        return array_merge($data, $params ?? []);
    }

    protected function handleException(Exception $e): ResponseInterface
    {
        // Log the full error for server-side monitoring
        log_message('error', '[' . get_class($e) . '] ' . $e->getMessage() . "\n" . $e->getTraceAsString());

        $resultObject = \App\Support\ExceptionFormatter::format($e);

        return $this->respond($resultObject->body, $resultObject->status);
    }


    protected function getUserId(): ?int
    {
        return $this->request instanceof ApiRequest ? $this->request->getAuthUserId() : null;
    }

    protected function getUserRole(): ?string
    {
        return $this->request instanceof ApiRequest ? $this->request->getAuthUserRole() : null;
    }
}
