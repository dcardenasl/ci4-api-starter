<?php

declare(strict_types=1);

namespace App\Controllers;

use App\HTTP\ApiRequest;
use App\Libraries\ApiResponse;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

/**
 * Base API Controller
 *
 * Provides standardized CRUD operations and request handling.
 * Child controllers only need to define $serviceName.
 */
abstract class ApiController extends Controller
{
    use ResponseTrait;

    /**
     * Service name to load from Config\Services
     * Override in child controllers
     */
    protected string $serviceName = '';

    /**
     * Cached service instance
     */
    protected ?object $service = null;

    /**
     * Custom status codes per method
     * Override in child controllers if needed
     *
     * @var array<string, int>
     */
    protected array $statusCodes = [
        'store'   => 201,
        'upload'  => 201,
        'destroy' => 200,
        'delete'  => 200,
    ];

    /**
     * Get the service instance
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
     * Get HTTP status code for successful operations
     */
    protected function getSuccessStatus(string $method): int
    {
        return $this->statusCodes[$method] ?? 200;
    }

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index');
    }

    public function show(string|int|null $id = null): ResponseInterface
    {
        return $this->handleRequest('show', ['id' => $id]);
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest('store');
    }

    public function update(string|int|null $id = null): ResponseInterface
    {
        return $this->handleRequest('update', ['id' => $id]);
    }

    public function delete(string|int|null $id = null): ResponseInterface
    {
        return $this->handleRequest('destroy', ['id' => $id]);
    }

    /**
     * Override respond to ensure all DTOs are recursively converted to arrays.
     */
    public function respond(mixed $data = null, ?int $status = null, string $message = ''): ResponseInterface
    {
        if ($data !== null) {
            $data = ApiResponse::convertDataToArrays($data);
        }

        return $this->response->setJSON($data)->setStatusCode($status ?? 200);
    }

    /**
     * Handle an API request by delegating to the service layer
     */
    protected function handleRequest(string|callable $target, ?array $params = null): ResponseInterface
    {
        try {
            if (is_callable($target)) {
                $result = $target();
            } else {
                $data = $this->collectRequestData($params);
                $result = $this->getService()->$target($data);
            }

            // If result is not already an ApiResponse structure (with 'status'), wrap it
            if (is_array($result) && !isset($result['status'])) {
                $result = ApiResponse::success($result);
            } elseif ($result instanceof \App\Interfaces\DataTransferObjectInterface) {
                $result = ApiResponse::success($result->toArray());
            }

            $status = $this->determineStatus((array) $result, is_string($target) ? $target : 'custom');

            return $this->respond($result, $status);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get a validated DTO from the request data
     *
     * @template T of \App\Interfaces\DataTransferObjectInterface
     * @param class-string<T> $dtoClass
     * @return T
     */
    protected function getDTO(string $dtoClass, ?array $params = null): object
    {
        $data = $this->collectRequestData($params);

        /** @var T */
        return new $dtoClass($data);
    }

    /**
     * Collect all request data from various sources
     */
    protected function collectRequestData(?array $params = null): array
    {
        $contentType = (string) $this->request->header('Content-Type');
        $isJson = str_contains($contentType, 'application/json');

        // Start with basic sources (GET is always allowed)
        $data = $this->request->getGet() ?? [];

        // For non-JSON requests, merge POST
        if (!$isJson) {
            $postData = $this->request->getPost() ?? [];
            $data = array_merge($data, $postData);
        }

        // Merge files
        $files = $this->request->getFiles();
        if (!empty($files)) {
            $data = array_merge($data, $files);
        }

        // Merge JSON or Raw Input
        if ($isJson) {
            $jsonData = $this->getJsonData();
            $data = array_merge($data, $jsonData);
        } else {
            // For other types (like multipart/form-data), check if we have raw input
            // but only if POST was empty
            if (empty($postData)) {
                $rawInput = $this->request->getRawInput();
                if (!empty($rawInput)) {
                    $data = array_merge($data, $rawInput);
                }
            }
        }

        // Add authenticated user ID and role if available
        if ($authUserId = $this->getUserId()) {
            $data['user_id'] = $authUserId;
        }
        if ($authUserRole = $this->getUserRole()) {
            $data['user_role'] = $authUserRole;
        }

        return $this->sanitizeInput($data);
    }

    protected function getJsonData(): array
    {
        $body = $this->request->getBody();

        if (empty($body)) {
            return [];
        }

        $json = json_decode((string)$body, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($json)) ? $json : [];
    }

    protected function sanitizeInput(array $data): array
    {
        return array_map(function ($value) {
            if ($value instanceof \CodeIgniter\HTTP\Files\UploadedFile) {
                return $value;
            }
            if (is_string($value)) {
                if (strlen($value) > 2048) {
                    return trim($value);
                }
                return strip_tags(trim($value));
            }
            if (is_array($value)) {
                return $this->sanitizeInput($value);
            }
            return $value;
        }, $data);
    }

    protected function determineStatus(array $result, string $method): int
    {
        return isset($result['errors'])
            ? ResponseInterface::HTTP_BAD_REQUEST
            : $this->resolveSuccessStatus($method, $result);
    }

    protected function resolveSuccessStatus(string $method, array $result): int
    {
        return $this->getSuccessStatus($method);
    }

    protected function handleException(Exception $e): ResponseInterface
    {
        log_message('error', 'API Exception: ' . $e->getMessage());
        log_message('error', 'Trace: ' . $e->getTraceAsString());

        if ($e instanceof \App\Exceptions\ApiException) {
            return $this->respond($e->toArray(), $e->getStatusCode());
        }

        if ($e instanceof \CodeIgniter\Database\Exceptions\DatabaseException) {
            log_message('critical', 'Database error: ' . $e->getMessage());
            return $this->respond([
                'status' => 'error',
                'message' => lang('Api.databaseError'),
                'errors' => [],
            ], 500);
        }

        $message = ENVIRONMENT === 'production'
            ? lang('Api.serverError')
            : $e->getMessage();

        return $this->respond([
            'status' => 'error',
            'message' => $message,
            'errors' => [],
        ], 500);
    }

    protected function getUserId(): ?int
    {
        return $this->request instanceof ApiRequest
            ? $this->request->getAuthUserId()
            : null;
    }

    protected function getUserRole(): ?string
    {
        return $this->request instanceof ApiRequest
            ? $this->request->getAuthUserRole()
            : null;
    }

    protected function respondCreated(array $data = []): ResponseInterface
    {
        return $this->respond($data, 201);
    }

    protected function respondNoContent(): ResponseInterface
    {
        return $this->respond(null, 204);
    }

    protected function respondNotFound(?string $message = null): ResponseInterface
    {
        return $this->respond(ApiResponse::notFound($message), 404);
    }

    protected function respondUnauthorized(?string $message = null): ResponseInterface
    {
        return $this->respond(ApiResponse::unauthorized($message), 401);
    }

    protected function respondValidationError(array $errors): ResponseInterface
    {
        return $this->respond(ApiResponse::validationError($errors), 422);
    }
}
