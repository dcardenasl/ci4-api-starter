<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\HTTP\ApiRequest;
use App\Libraries\ApiResponse;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

/**
 * Base API Controller
 *
 * Provides standardized request handling and automated DTO validation.
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

            if ($dtoClass !== null && class_exists($dtoClass)) {
                // Instantiate DTO (this performs automatic self-validation)
                $requestDto = new $dtoClass($data);
                $result = is_callable($target) ? $target($requestDto) : $this->getService()->$target($requestDto);
            } else {
                // Fallback for simple requests (data passed as array)
                $result = is_callable($target) ? $target($data) : $this->getService()->$target($data);
            }

            // If result is already a response, return it directly
            if ($result instanceof ResponseInterface) {
                return $result;
            }

            // Standardize response structure
            if ($result instanceof \App\Interfaces\DataTransferObjectInterface) {
                $finalResult = ApiResponse::success($result->toArray());
            } elseif (is_array($result)) {
                if (!isset($result['status'])) {
                    $finalResult = ApiResponse::success($result);
                } elseif ($result['status'] === 'success' && !isset($result['data'])) {
                    // Handle cases where service returns success array without data key
                    $successData = (array) $result;
                    unset($successData['status'], $successData['message']);
                    $finalResult = ApiResponse::success($successData, (string) ($result['message'] ?? ''));
                } else {
                    $finalResult = $result;
                }
            } else {
                $finalResult = ApiResponse::success((array) $result);
            }

            $methodName = is_string($target) ? $target : '';
            $status = $methodName !== '' ? ($this->statusCodes[$methodName] ?? null) : null;

            // Decision: Strict Success Codes (202 for pending)
            if (is_array($finalResult) && isset($finalResult['message'])) {
                $msg = strtolower((string) $finalResult['message']);
                if (str_contains($msg, 'pending') || str_contains($msg, 'pendiente')) {
                    $status = 202;
                }
            }

            // Standardize response structure
            return $this->respond($finalResult, $status);
        } catch (ValidationException $e) {
            return $this->respond($e->toArray(), 422);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
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

        $data = array_merge(
            (array)$request->getGet(),
            (array)$request->getPost(),
            (array)$request->getJSON(true),
            (array)$request->getRawInput(),
            $request->getFiles(),
            $params ?? []
        );

        // Inject authentication context if available
        if ($authUserId = $this->getUserId()) {
            $data['user_id'] = $authUserId;
        }
        if ($authUserRole = $this->getUserRole()) {
            $data['user_role'] = $authUserRole;
        }

        return $data;
    }

    protected function handleException(Exception $e): ResponseInterface
    {
        log_message('error', '[' . get_class($e) . '] ' . $e->getMessage());

        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

        if ($e instanceof \App\Exceptions\ApiException) {
            return $this->respond($e->toArray(), $statusCode);
        }

        // Environment-aware error reporting
        $message = (ENVIRONMENT === 'production') ? lang('Api.serverError') : $e->getMessage();

        return $this->respond([
            'status' => 'error',
            'message' => $message,
            'errors' => []
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
