<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Interfaces\UserServiceInterface;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(private readonly UserServiceInterface $userService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $this->userService->create($request->validated());

        return response()->json(
            ['message' => __('auth.register_success')],
            201
        );
    }

    public function verify(int $id, string $hash): JsonResponse
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

    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => __('auth.verification_sent')]);
    }
}
