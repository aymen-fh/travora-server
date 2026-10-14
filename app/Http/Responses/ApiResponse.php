<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

final class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => self::normalizeData($data),
        ];

        if ($data instanceof AbstractPaginator) {
            $payload['meta'] = array_merge(self::paginatorMeta($data), $meta);
            $payload['data'] = $data->items();
        } elseif ($meta !== []) {
            $payload['meta'] = $meta;
        } else {
            $payload['meta'] = (object) [];
        }

        return response()->json($payload, $status);
    }

    public static function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function accepted(mixed $data = null, string $message = 'Accepted'): JsonResponse
    {
        return self::success($data, $message, 202);
    }

    public static function error(
        string $message,
        int $status = 400,
        array $errors = [],
        ?string $code = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => $errors === [] ? (object) [] : $errors,
            'code' => $code ?? 'ERROR',
        ];

        return response()->json($payload, $status);
    }

    private static function normalizeData(mixed $data): mixed
    {
        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->resolve();
        }

        return $data;
    }

    /**
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    private static function paginatorMeta(AbstractPaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
