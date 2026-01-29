<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\IncomingRequest;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Base API Controller
 *
 * Provides a standardized way to handle API requests in CodeIgniter 4 applications.
 * Handles multiple data sources (GET, POST, JSON, Files) and provides unified error responses.
 *
 * @package App\Controllers
 * @author Your Name <your.email@example.com>
 * @license MIT
 * @version 1.0.0
 */
abstract class ApiController extends Controller
{
    use ResponseTrait;

    /**
     * @var IncomingRequest
     */
    protected $request;

    /**
     * Get the service instance that handles business logic
     *
     * @return object Service instance
     */
    abstract protected function getService(): object;

    /**
     * Get the appropriate HTTP status code for successful operations
     *
     * @param string $method The service method name
     * @return int HTTP status code
     */
    abstract protected function getSuccessStatus(string $method): int;

    /**
     * Handle an API request by delegating to the service layer
     *
     * @param string $method Service method to call
     * @param array|null $item Additional data to merge with request
     * @return ResponseInterface JSON response
     */
    protected function handleRequest(string $method, ?array $item = null): ResponseInterface
    {
        try {
            $requestData = $this->collectRequestData($item);
            $result = $this->getService()->$method($requestData);
            $status = $this->determineStatus($result, $method);

            return $this->respond($result, $status);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Collect all request data from various sources
     *
     * Merges data from GET, POST, raw input, uploaded files, JSON body, and optional item array.
     *
     * @param array|null $item Additional data to merge (e.g., route parameters)
     * @return array Combined request data
     */
    protected function collectRequestData(?array $item = null): array
    {
        $requestData = array_merge(
            $this->request->getGet() ?? [],
            $this->request->getPost() ?? [],
            $this->request->getRawInput(),
            $this->request->getFiles(),
            $this->getJsonData(),
            $item ?? []
        );

        // Sanitize input to prevent XSS attacks
        return $this->sanitizeInput($requestData);
    }

    /**
     * Recursively sanitize input data
     *
     * Strips HTML tags and trims whitespace from string values.
     * Protects against Stored XSS attacks.
     *
     * @param array $data Input data to sanitize
     * @return array Sanitized data
     */
    protected function sanitizeInput(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return strip_tags(trim($value));
            }
            if (is_array($value)) {
                return $this->sanitizeInput($value);
            }
            return $value;
        }, $data);
    }

    /**
     * Get uploaded file from request
     *
     * @param string $fieldName Field name in the request (default: 'file')
     * @return array Array containing the uploaded file
     */
    protected function getFileInput(string $fieldName = 'file'): array
    {
        $file = $this->request->getFile($fieldName);
        return [$fieldName => $file];
    }

    /**
     * Parse JSON data from request body
     *
     * Safely decodes JSON and returns empty array on errors.
     *
     * @return array Decoded JSON data or empty array
     */
    protected function getJsonData(): array
    {
        $body = $this->request->getBody();

        if (empty($body)) {
            return [];
        }

        $jsonData = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return is_array($jsonData) ? $jsonData : [];
    }

    /**
     * Determine HTTP status code based on result
     *
     * @param array $result Service method result
     * @param string $method Service method name
     * @return int HTTP status code
     */
    protected function determineStatus(array $result, string $method): int
    {
        return isset($result['errors'])
            ? ResponseInterface::HTTP_BAD_REQUEST
            : $this->getSuccessStatus($method);
    }

    /**
     * Handle exceptions and return appropriate error response
     *
     * @param Exception $e The exception to handle
     * @return ResponseInterface JSON error response
     */
    protected function handleException(Exception $e): ResponseInterface
    {
        // Handle custom API exceptions
        if ($e instanceof \App\Exceptions\ApiException) {
            return $this->respond($e->toArray(), $e->getStatusCode());
        }

        // Handle standard exceptions
        $status = match (true) {
            $e instanceof InvalidArgumentException => ResponseInterface::HTTP_BAD_REQUEST,
            $e instanceof RuntimeException => ResponseInterface::HTTP_INTERNAL_SERVER_ERROR,
            default => ResponseInterface::HTTP_BAD_REQUEST,
        };

        return $this->respond([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], $status);
    }

    /**
     * Respond with created resource (201)
     *
     * @param array $data Response data
     * @return ResponseInterface
     */
    protected function respondCreated(array $data = []): ResponseInterface
    {
        return $this->respond($data, ResponseInterface::HTTP_CREATED);
    }

    /**
     * Respond with no content (204)
     *
     * Typically used for successful DELETE operations
     *
     * @return ResponseInterface
     */
    protected function respondNoContent(): ResponseInterface
    {
        return $this->respond(null, ResponseInterface::HTTP_NO_CONTENT);
    }

    /**
     * Respond with not found error (404)
     *
     * @param string $message Error message
     * @return ResponseInterface
     */
    protected function respondNotFound(string $message = 'Resource not found'): ResponseInterface
    {
        return $this->respond(['error' => $message], ResponseInterface::HTTP_NOT_FOUND);
    }

    /**
     * Respond with unauthorized error (401)
     *
     * @param string $message Error message
     * @return ResponseInterface
     */
    protected function respondUnauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return $this->respond(['error' => $message], ResponseInterface::HTTP_UNAUTHORIZED);
    }

    /**
     * Respond with validation error (422)
     *
     * @param array $errors Validation errors
     * @return ResponseInterface
     */
    protected function respondValidationError(array $errors): ResponseInterface
    {
        return $this->respond(['errors' => $errors], ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
    }
}
