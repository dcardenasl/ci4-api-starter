<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Interfaces\DataTransferObjectInterface;
use App\Support\ApiResult;
use App\Support\OperationResult;
use JsonSerializable;

/**
 * API Response Builder
 *
 * Centralizes API response format for consistency.
 * Provides static methods for building success and error responses.
 */
class ApiResponse
{
    /**
     * Build a successful response
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
            $response['data'] = self::convertDataToArrays($data);
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * Build an error response
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
     * Create a standard response object from any service result
     */
    public static function fromResult(mixed $result, string $methodName = '', array $statusCodes = []): ApiResult
    {
        if ($result instanceof ApiResult) {
            return $result;
        }

        $defaultStatus = $statusCodes[$methodName] ?? 200;

        return match (true) {
            $result instanceof OperationResult => self::handleOperationResult($result, $defaultStatus),
            $result instanceof DataTransferObjectInterface => self::handleDto($result, $defaultStatus),
            is_bool($result) => self::handleBoolean($result, $methodName, $defaultStatus),
            is_array($result) => self::handleArray($result, $defaultStatus),
            default => new ApiResult(self::success((array) $result), $defaultStatus),
        };
    }

    private static function handleOperationResult(OperationResult $result, int $defaultStatus): ApiResult
    {
        $status = $result->httpStatus;
        if ($status === null) {
            $status = $result->isAccepted() ? 202 : $defaultStatus;
        }

        if ($result->isError()) {
            $body = self::error($result->errors, $result->message, $status);
        } else {
            $body = self::success($result->data, $result->message);
        }

        return new ApiResult($body, $status);
    }

    private static function handleDto(DataTransferObjectInterface $result, int $status): ApiResult
    {
        $dtoData = $result->toArray();

        if (isset($dtoData['data'], $dtoData['total'], $dtoData['page'], $dtoData['perPage'])) {
            $body = self::paginated(
                (array) $dtoData['data'],
                (int) $dtoData['total'],
                (int) $dtoData['page'],
                (int) $dtoData['perPage']
            );
        } else {
            $body = self::success($dtoData);
        }

        return new ApiResult($body, $status);
    }

    private static function handleBoolean(bool $result, string $methodName, int $status): ApiResult
    {
        if ($result === true) {
            if (in_array($methodName, ['destroy', 'delete'], true)) {
                $body = self::deleted();
            } else {
                $body = self::success(['success' => true]);
            }
        } else {
            $body = self::success([]);
        }

        return new ApiResult($body, $status);
    }

    private static function handleArray(array $result, int $status): ApiResult
    {
        if (isset($result['data'], $result['total'], $result['page'], $result['perPage'])) {
            $body = self::paginated(
                $result['data'],
                $result['total'],
                (int) $result['page'],
                (int) $result['perPage']
            );
        } elseif (!isset($result['status'])) {
            $body = self::success($result);
        } elseif (($result['status'] ?? '') === 'success' && !isset($result['data'])) {
            $successData = $result;
            unset($successData['status'], $successData['message']);
            $body = self::success($successData, (string) ($result['message'] ?? ''));
        } else {
            $body = $result;
        }

        return new ApiResult($body, $status);
    }

    /**
     * Recursively convert data to arrays, supporting DTOs, JsonSerializable, and toArray() objects.
     */
    public static function convertDataToArrays(mixed $data): mixed
    {
        if ($data instanceof DataTransferObjectInterface) {
            return $data->toArray();
        }

        if ($data instanceof JsonSerializable) {
            return $data->jsonSerialize();
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = self::convertDataToArrays($value);
            }
            return $result;
        }

        return $data;
    }

    /**
     * Build a paginated response
     */
    public static function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage
    ): array {
        return self::success($items, null, [
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page,
            'lastPage' => (int) ceil($total / $perPage),
            'from' => ($page - 1) * $perPage + 1,
            'to' => min($page * $perPage, $total),
        ]);
    }

    public static function created(mixed $data, ?string $message = null): array
    {
        return self::success($data, $message ?? lang('Api.resourceCreated'));
    }

    public static function deleted(?string $message = null): array
    {
        return self::success(null, $message ?? lang('Api.resourceDeleted'));
    }

    public static function validationError(array $errors, ?string $message = null): array
    {
        return self::error($errors, $message ?? lang('Api.validationFailed'), 422);
    }

    public static function notFound(?string $message = null): array
    {
        return self::error([], $message ?? lang('Api.resourceNotFound'), 404);
    }

    public static function unauthorized(?string $message = null): array
    {
        return self::error([], $message ?? lang('Api.unauthorized'), 401);
    }

    public static function forbidden(?string $message = null): array
    {
        return self::error([], $message ?? lang('Api.forbidden'), 403);
    }

    public static function serverError(?string $message = null): array
    {
        return self::error([], $message ?? lang('Api.serverError'), 500);
    }
}
