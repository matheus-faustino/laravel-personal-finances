<?php

namespace App\Interfaces;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface TransactionServiceInterface
{
    public function getAllForUser(User $user): Collection;

    public function create(User $user, array $data): Transaction;

    public function update(Transaction $transaction, array $data): Transaction;

    public function delete(Transaction $transaction): void;
}
