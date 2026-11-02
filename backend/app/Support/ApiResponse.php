<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    public static function success(
        string $message,
        array $data = [],
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => (object) $meta,
        ], $status);
    }

    public static function error(
        Request $request,
        string $message,
        string $code,
        int $status,
        array $errors = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
            'code' => $code,
            'request_id' => $request->attributes->get('request_id'),
        ], $status);
    }
}
