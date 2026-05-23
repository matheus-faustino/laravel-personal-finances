<?php

namespace App\Services;

use App\Interfaces\UserServiceInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserService implements UserServiceInterface
{
    /** {@inheritDoc} */
    public function getAll(): Collection
    {
        return User::all();
    }

    /** {@inheritDoc} */
    public function create(array $data): User
    {
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        $user->sendEmailVerificationNotification();

        return $user;
    }

    /** {@inheritDoc} */
    public function update(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $user;
    }

    /** {@inheritDoc} */
    public function delete(User $user): void
    {
        $user->delete();
    }
}
