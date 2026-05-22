<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json(
            ['message' => __('auth.register_success')],
            201
        );
    }

    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json(['message' => __('auth.invalid_verification_link')], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('auth.email_already_verified')]);
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        return response()->json(['message' => __('auth.email_verified')]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::where('email', $request->email)->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => __('auth.verification_sent')]);
    }
}
