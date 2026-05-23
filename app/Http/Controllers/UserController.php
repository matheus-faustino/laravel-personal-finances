<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Interfaces\UserServiceInterface;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(private readonly UserServiceInterface $userService) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('view-any-user');

        return UserResource::collection($this->userService->getAll());
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view-user', $user);

        return new UserResource($user);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        Gate::authorize('create-user');

        $user = $this->userService->create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        Gate::authorize('update-user', $user);

        $data = $request->validated();

        if (! $request->user()->isAdmin()) {
            unset($data['role']);
        }

        return new UserResource($this->userService->update($user, $data));
    }

    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('delete-user', $user);

        $this->userService->delete($user);

        return response()->json(null, 204);
    }
}
