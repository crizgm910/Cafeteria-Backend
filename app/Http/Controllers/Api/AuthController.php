<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $email = Str::lower(trim($request->string('email')->toString()));
        $user = User::where('email', $email)->first();

        if (!$user || !$user->active || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        $user->tokens()->where('name', 'admin_token')->delete();

        return response()->json([
            'token' => $user->createToken('admin_token')->plainTextToken,
            'user' => array_merge($user->toArray(), $user->authorizationData()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}
