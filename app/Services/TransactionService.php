<?php

namespace App\Services;

use App\Interfaces\TransactionServiceInterface;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TransactionService implements TransactionServiceInterface
{
    /** {@inheritDoc} */
    public function getAllForUser(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Transaction::all();
        }

        return Transaction::where('user_id', $user->id)->get();
    }

    /** {@inheritDoc} */
    public function getAllForDocument(Document $document): Collection
    {
        return Transaction::where('document_id', $document->id)->get();
    }

    /** {@inheritDoc} */
    public function get(int $transactionId): Transaction
    {
        return Transaction::findOrFail($transactionId);
    }

    /** {@inheritDoc} */
    public function create(User $user, array $data): Transaction
    {
        $data['user_id'] = $user->id;

        return Transaction::create($data);
    }

    /** {@inheritDoc} */
    public function update(Transaction $transaction, array $data): Transaction
    {
        $transaction->update($data);

        return $transaction;
    }

    /** {@inheritDoc} */
    public function delete(Transaction $transaction): void
    {
        $transaction->delete();
    }
}
