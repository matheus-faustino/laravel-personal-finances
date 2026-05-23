<?php

namespace App\Interfaces;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserServiceInterface
{
    /**
     * Returns all users.
     *
     * @return Collection<int, User>
     */
    public function getAll(): Collection;

    /**
     * Creates and persists a new user with the given data.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function create(array $data): User;

    /**
     * Updates the given user with the provided data.
     *
     * @param  array{name: string, email: string, password?: string|null}  $data
     */
    public function update(User $user, array $data): User;

    /**
     * Deletes the given user.
     */
    public function delete(User $user): void;
}
