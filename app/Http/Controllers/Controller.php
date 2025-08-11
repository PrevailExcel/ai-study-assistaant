<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function success($data = null, $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public function error($errorMessage, $statusCode = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $errorMessage
        ], $statusCode);
    }
}
