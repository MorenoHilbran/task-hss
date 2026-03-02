<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    // Hardcoded credentials as per assessment spec
    private const EMAIL    = 'admin@test.com';
    private const PASSWORD = '123456';
    private const TOKEN    = 'simple-admin-token-2024';

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($request->email === self::EMAIL && $request->password === self::PASSWORD) {
            return $this->jsonResponse(true, [
                'token' => self::TOKEN,
                'type'  => 'Bearer',
            ], 'Login berhasil', 200);
        }

        return $this->jsonResponse(false, null, 'Email atau password salah', 401);
    }
}
