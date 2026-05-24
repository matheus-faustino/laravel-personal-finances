<?php

namespace App\Services;

use App\Interfaces\TransactionServiceInterface;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TransactionService implements TransactionServiceInterface
{
    /** {@inheritDoc} */
    public function getAllForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $user->isAdmin()
            ? Transaction::query()
            : Transaction::where('user_id', $user->id);

        if (! empty($filters['start_date'])) {
            $query->whereDate('date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('date', '<=', $filters['end_date']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        return $query->paginate(isset($filters['per_page']) ? (int) $filters['per_page'] : 15);
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
