<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\HTTP\ApiRequest;
use App\Libraries\ApiResponse;
use App\Libraries\ContextHolder;
use App\Support\OperationResult;
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
            $securityContext = $this->establishSecurityContext($data);

            $result = $this->executeTarget($target, $dtoClass, $data, $securityContext);

            return $this->processResult($result, $target);
        } catch (ValidationException $e) {
            return $this->respond($e->toArray(), 422);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Establish the security context and inject it into the data array
     */
    private function establishSecurityContext(array &$data): \App\DTO\SecurityContext
    {
        $securityContext = new \App\DTO\SecurityContext($this->getUserId(), $this->getUserRole());

        // TESTING BYPASS: If a context was established by the test, prioritize it
        if (ENVIRONMENT === 'testing') {
            $testContext = ContextHolder::get();
            if ($testContext !== null && $testContext->userId !== null) {
                $securityContext = $testContext;
            }
        }

        // Set context globally for this request
        ContextHolder::set($securityContext);

        if (!isset($data['user_id']) && $securityContext->userId !== null) {
            $data['user_id'] = $securityContext->userId;
        }
        if (!isset($data['user_role']) && $securityContext->role !== null) {
            $data['user_role'] = $securityContext->role;
        }

        return $securityContext;
    }

    /**
     * Execute the target service method or callable
     */
    private function executeTarget(string|callable $target, ?string $dtoClass, array $data, \App\DTO\SecurityContext $context): mixed
    {
        if ($dtoClass !== null && class_exists($dtoClass)) {
            // Instantiate DTO (this performs automatic self-validation)
            $requestDto = new $dtoClass($data);
            return is_callable($target) ? $target($requestDto, $context) : $this->getService()->$target($requestDto, $context);
        }

        // Fallback for simple requests (data passed as array)
        return is_callable($target) ? $target($data, $context) : $this->getService()->$target($data, $context);
    }

    /**
     * Normalize the result into a standard API response
     */
    private function processResult(mixed $result, string|callable $target): ResponseInterface
    {
        // If result is already a response, return it directly
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        $methodName = is_string($target) ? $target : '';

        // 1. Handle OperationResult
        if ($result instanceof OperationResult) {
            return $this->processOperationResult($result, $methodName);
        }

        // 2. Handle DTOs
        if ($result instanceof \App\Interfaces\DataTransferObjectInterface) {
            return $this->processDtoResult($result, $methodName);
        }

        // 3. Handle Booleans
        if (is_bool($result)) {
            return $this->processBooleanResult($result, $methodName);
        }

        // 4. Handle Arrays and other types
        return $this->processGenericResult($result, $methodName);
    }

    private function processOperationResult(OperationResult $result, string $methodName): ResponseInterface
    {
        $status = $result->httpStatus;
        if ($status === null) {
            $status = $result->isAccepted()
                ? 202
                : ($this->statusCodes[$methodName] ?? 200);
        }

        if ($result->isError()) {
            $message = $result->message ?? lang('Api.requestFailed');
            $finalResult = ApiResponse::error($result->errors, $message, $status);
        } else {
            $finalResult = ApiResponse::success($result->data, $result->message);
        }

        return $this->respond($finalResult, $status);
    }

    private function processDtoResult(\App\Interfaces\DataTransferObjectInterface $result, string $methodName): ResponseInterface
    {
        $dtoData = $result->toArray();

        if (isset($dtoData['data'], $dtoData['total'], $dtoData['page'], $dtoData['perPage'])) {
            $finalResult = ApiResponse::paginated(
                (array) $dtoData['data'],
                (int) $dtoData['total'],
                (int) $dtoData['page'],
                (int) $dtoData['perPage']
            );
        } else {
            $finalResult = ApiResponse::success($dtoData);
        }

        $status = $this->statusCodes[$methodName] ?? null;
        return $this->respond($finalResult, $status);
    }

    private function processBooleanResult(bool $result, string $methodName): ResponseInterface
    {
        if ($result === true) {
            if (in_array($methodName, ['destroy', 'delete'], true)) {
                $finalResult = ApiResponse::deleted();
            } else {
                $finalResult = ApiResponse::success(['success' => true]);
            }
        } else {
            // Original logic for false: (array)false => []
            $finalResult = ApiResponse::success([]);
        }

        $status = $this->statusCodes[$methodName] ?? null;
        return $this->respond($finalResult, $status);
    }

    private function processGenericResult(mixed $result, string $methodName): ResponseInterface
    {
        if (is_array($result)) {
            // Detect Pagination Structure
            if (isset($result['data'], $result['total'], $result['page'], $result['perPage'])) {
                $finalResult = ApiResponse::paginated(
                    $result['data'],
                    $result['total'],
                    (int) $result['page'],
                    (int) $result['perPage']
                );
            } elseif (!isset($result['status'])) {
                $finalResult = ApiResponse::success($result);
            } elseif ($result['status'] === 'success' && !isset($result['data'])) {
                $successData = (array) $result;
                unset($successData['status'], $successData['message']);
                $finalResult = ApiResponse::success($successData, (string) ($result['message'] ?? ''));
            } else {
                $finalResult = $result;
            }
        } else {
            $finalResult = ApiResponse::success((array) $result);
        }

        $status = $this->statusCodes[$methodName] ?? null;

        return $this->respond($finalResult, $status);
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

        // Determine status if not provided
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

        return array_merge(
            (array)$request->getGet(),
            (array)$request->getPost(),
            (array)$request->getJSON(true),
            (array)$request->getRawInput(),
            $request->getFiles(),
            $params ?? []
        );
    }

    protected function handleException(Exception $e): ResponseInterface
    {
        log_message('error', '[' . get_class($e) . '] ' . $e->getMessage() . "\n" . $e->getTraceAsString());

        $statusCode = 500;
        if ($e instanceof \App\Interfaces\HasStatusCode) {
            $statusCode = $e->getStatusCode();
        } elseif (method_exists($e, 'getStatusCode')) {
            /** @var mixed $dynamicException */
            $dynamicException = $e;
            $statusCode = (int) $dynamicException->getStatusCode();
        }

        if ($e instanceof \App\Exceptions\ApiException) {
            return $this->respond($e->toArray(), $statusCode);
        }

        // Environment-aware error reporting
        $message = (ENVIRONMENT === 'production') ? lang('Api.serverError') : (get_class($e) . ': ' . $e->getMessage());

        return $this->respond([
            'status' => 'error',
            'message' => $message,
            'errors' => (ENVIRONMENT === 'testing') ? ['trace' => explode("\n", $e->getTraceAsString())] : []
        ], $statusCode);
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
