<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * API Response Builder
 *
 * Centralizes API response format for consistency.
 * Provides static methods for building success and error responses.
 *
 * @package App\Libraries
 */
class ApiResponse
{
    /**
     * Build a successful response
     *
     * @param mixed $data Response data (can be array, object, null)
     * @param string|null $message Optional success message
     * @param array $meta Optional metadata (pagination, links, etc.)
     * @return array Formatted success response
     *
     * @example
     * ApiResponse::success(['user' => $userData], 'User retrieved')
     * // Returns: ['status' => 'success', 'message' => '...', 'data' => [...]]
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        array $meta = []
    ): array {
        $response = ['status' => 'success'];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * Build an error response
     *
     * @param array|string $errors Error details (array of errors or single error string)
     * @param string|null $message Main error message
     * @param int|null $code Optional error code
     * @return array Formatted error response
     *
     * @example
     * ApiResponse::error(['email' => 'Invalid email'], 'Validation failed')
     * // Returns: ['status' => 'error', 'message' => '...', 'errors' => [...]]
     */
    public static function error(
        array|string $errors,
        ?string $message = null,
        ?int $code = null
    ): array {
        $message = $message ?? lang('Api.requestFailed');
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if (is_string($errors)) {
            $response['errors'] = ['general' => $errors];
        } else {
            $response['errors'] = $errors;
        }

        if ($code !== null) {
            $response['code'] = $code;
        }

        return $response;
    }

    /**
     * Build a paginated response
     *
     * @param array $items Items for current page
     * @param int $total Total number of items across all pages
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return array Formatted paginated response with meta
     *
     * @example
     * ApiResponse::paginated($users, 100, 1, 10)
     * // Includes pagination meta: total, per_page, current_page, etc.
     */
    public static function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage
    ): array {
        return self::success($items, null, [
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total),
            ],
        ]);
    }

    /**
     * Build a created response (HTTP 201)
     *
     * @param mixed $data Created resource data
     * @param string|null $message Success message
     * @return array Formatted response
     */
    public static function created(
        mixed $data,
        ?string $message = null
    ): array {
        return self::success($data, $message ?? lang('Api.resourceCreated'));
    }

    /**
     * Build a deleted response (HTTP 200/204)
     *
     * @param string|null $message Success message
     * @return array Formatted response
     */
    public static function deleted(
        ?string $message = null
    ): array {
        return self::success(null, $message ?? lang('Api.resourceDeleted'));
    }

    /**
     * Build a validation error response (HTTP 422)
     *
     * @param array $errors Validation errors
     * @param string|null $message Error message
     * @return array Formatted response
     */
    public static function validationError(
        array $errors,
        ?string $message = null
    ): array {
        return self::error($errors, $message ?? lang('Api.validationFailed'), 422);
    }

    /**
     * Build a not found response (HTTP 404)
     *
     * @param string|null $message Error message
     * @return array Formatted response
     */
    public static function notFound(
        ?string $message = null
    ): array {
        return self::error([], $message ?? lang('Api.resourceNotFound'), 404);
    }

    /**
     * Build an unauthorized response (HTTP 401)
     *
     * @param string|null $message Error message
     * @return array Formatted response
     */
    public static function unauthorized(
        ?string $message = null
    ): array {
        return self::error([], $message ?? lang('Api.unauthorized'), 401);
    }

    /**
     * Build a forbidden response (HTTP 403)
     *
     * @param string|null $message Error message
     * @return array Formatted response
     */
    public static function forbidden(
        ?string $message = null
    ): array {
        return self::error([], $message ?? lang('Api.forbidden'), 403);
    }

    /**
     * Build a server error response (HTTP 500)
     *
     * @param string|null $message Error message
     * @return array Formatted response
     */
    public static function serverError(
        ?string $message = null
    ): array {
        return self::error([], $message ?? lang('Api.serverError'), 500);
    }
}
