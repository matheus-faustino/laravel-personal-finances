<?php

namespace App\Services;

use App\Interfaces\TransactionServiceInterface;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TransactionService implements TransactionServiceInterface
{
    public function getAllForUser(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Transaction::all();
        }

        return Transaction::where('user_id', $user->id)->get();
    }

    public function getAllForDocument(Document $document): Collection
    {
        return Transaction::where('document_id', $document->id)->get();
    }

    public function get(int $transactionId): Transaction
    {
        return Transaction::findOrFail($transactionId);
    }

    public function create(User $user, array $data): Transaction
    {
        $data['user_id'] = $user->id;

        return Transaction::create($data);
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        $transaction->update($data);

        return $transaction;
    }

    public function delete(Transaction $transaction): void
    {
        $transaction->delete();
    }
}
