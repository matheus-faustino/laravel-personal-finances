<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => __('auth.invalid_credentials')], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasVerifiedEmail()) {
            Auth::logout();

            return response()->json(['message' => __('auth.email_not_verified')], 403);
        }

        $user->tokens()->where('name', 'api-token')->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('auth.logout_success')]);
    }
}
