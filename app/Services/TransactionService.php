<?php

namespace App\Services;

use App\Interfaces\TransactionServiceInterface;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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

        if (! empty($filters['document_id'])) {
            $query->where('document_id', (int) $filters['document_id']);
        }

        return $query->with('category')->paginate(isset($filters['per_page']) ? (int) $filters['per_page'] : 15);
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

    /** {@inheritDoc} */
    public function bulkUpdate(User $user, array $transactionsData): Collection
    {
        return DB::transaction(function () use ($user, $transactionsData): Collection {
            $updatedTransactions = [];

            foreach ($transactionsData as $data) {
                $transaction = Transaction::findOrFail($data['id']);

                if (Gate::forUser($user)->denies('modify-transaction', $transaction)) {
                    throw new AuthorizationException(
                        "You are not authorized to modify transaction {$transaction->id}."
                    );
                }

                $updateData = collect($data)->except(['id', 'user_id', 'document_id'])->toArray();

                $transaction->update($updateData);
                $updatedTransactions[] = $transaction;
            }

            return Collection::make($updatedTransactions)->load('category');
        });
    }
}
