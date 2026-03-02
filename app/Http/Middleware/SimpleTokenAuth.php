<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SimpleTokenAuth
{
    private const VALID_TOKEN = 'simple-admin-token-2024';

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (!$bearer || $bearer !== self::VALID_TOKEN) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Unauthorized. Token tidak valid atau tidak ditemukan.',
            ], 401);
        }

        return $next($request);
    }
}
