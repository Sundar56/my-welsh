<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{
    /**
     * Return a success response.
     *
     * @param array $result
     * @param string $message
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResponse($result, $message): JsonResponse
    {
        $response = [
            'status' => 200,
            'data' => $result,
            'message' => $message,
        ];

        return response()->json($response, 200);
    }

    /**
     * Return an error response.
     *
     * @param string $error
     * @param array $errorMessages
     * @param int $code
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendError($error, $errorMessages = [], $code = 404): JsonResponse
    {
        $response = [
            'status' => $code,
            'message' => $error,
        ];

        if (! is_null($errorMessages) && $errorMessages) {
            $response['data'] = $errorMessages;
        }
        return response()->json($response, $code);
    }
}
