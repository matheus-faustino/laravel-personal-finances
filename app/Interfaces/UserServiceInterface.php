<?php

namespace App\Interfaces;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserServiceInterface
{
    public function getAll(): Collection;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function delete(User $user): void;
}
