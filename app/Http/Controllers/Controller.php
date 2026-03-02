<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected function jsonResponse($success, $data, $message, $status = 200)
    {
        return response()->json([
            'success' => $success,
            'data'    => $data,
            'message' => $message,
        ], $status);
    }
}
