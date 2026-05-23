<?php

namespace App\Services;

use App\Interfaces\UserServiceInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

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
        $user = User::create($data);

        $user->sendEmailVerificationNotification();

        return $user;
    }

    /** {@inheritDoc} */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    /** {@inheritDoc} */
    public function delete(User $user): void
    {
        $user->delete();
    }
}
