<?php

namespace App\Interfaces;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface TransactionServiceInterface
{
    public function index(User $user): Collection;

    public function show(Transaction $transaction): Transaction;

    public function store(User $user, array $data): Transaction;

    public function update(Transaction $transaction, array $data): Transaction;

    public function destroy(Transaction $transaction): void;
}
