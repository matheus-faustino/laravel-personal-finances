<?php

namespace App\Interfaces;

use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface TransactionServiceInterface
{
    public function getAllForUser(User $user): Collection;

    public function getAllForDocument(Document $document): Collection;

    public function get(int $transactionId): Transaction;

    public function create(User $user, array $data): Transaction;

    public function update(Transaction $transaction, array $data): Transaction;

    public function delete(Transaction $transaction): void;
}
