<?php

if (!function_exists('queueSuccess')) {
    function queueSuccess(array $messages) {
        return response()->json([
            'message' => 'Email message success sent to queue',
            'kode' => 200,
            'data' => $messages
        ], 200);
    }
}

if (!function_exists('invalidRequest')) {
    function invalidRequest(array $errors) {
        return response()->json([
            'error' => 'Invalid request',
            'kode' => 422,
            'messages' => $errors
        ], 422);
    }
}

if (!function_exists('invalidSecret')) {
    function invalidSecret() {
        return response()->json([
            'error' => 'Invalid secret key',
            'kode' => 403
        ], 403);
    }
}

if (!function_exists('queueError')) {
    function queueError(string $message) {
        return response()->json([
            'error' => 'Queue error occurred',
            'kode' => 500,
            'message' => $message
        ], 500);
    }
}

if (!function_exists('validationError')) {
    function validationError(array $errors) {
        return response()->json([
            'error' => 'Validation failed',
            'kode' => 422,
            'messages' => $errors
        ], 422);
    }
}

if (!function_exists('connectionError')) {
    function connectionError() {
        return response()->json([
            'error' => 'RabbitMQ connection error',
            'kode' => 503,
            'message' => 'Unable to connect to message queue service'
        ], 503);
    }
}

// Generic response helpers
if (!function_exists('responseSuccess')) {
    function responseSuccess(string $message) {
        return response()->json([
            'message' => $message,
            'kode' => 200
        ], 200);
    }
}

if (!function_exists('responseWithData')) {
    function responseWithData(string $message, $data) {
        return response()->json([
            'message' => $message,
            'kode' => 200,
            'data' => $data
        ], 200);
    }
}

if (!function_exists('errorResponse')) {
    function errorResponse(string $message, int $code = 400) {
        return response()->json([
            'error' => $message,
            'kode' => $code
        ], $code);
    }
}

if (!function_exists('errorWithData')) {
    function errorWithData(string $message, $data, int $code = 400) {
        return response()->json([
            'error' => $message,
            'kode' => $code,
            'data' => $data
        ], $code);
    }
}
