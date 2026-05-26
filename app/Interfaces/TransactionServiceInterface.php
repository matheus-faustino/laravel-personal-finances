<?php

namespace App\Interfaces;

use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TransactionServiceInterface
{
    /**
     * Returns a paginated list of transactions accessible by the given user,
     * optionally filtered by date range on the `date` column and by category.
     *
     * @param  array{start_date?: string|null, end_date?: string|null, per_page?: int|null, category_id?: int|null}  $filters
     */
    public function getAllForUser(User $user, array $filters = []): LengthAwarePaginator;

    /**
     * Returns all transactions belonging to the given document.
     *
     * @return Collection<int, Transaction>
     */
    public function getAllForDocument(Document $document): Collection;

    /**
     * Finds and returns a transaction by its ID, or throws a ModelNotFoundException.
     */
    public function get(int $transactionId): Transaction;

    /**
     * Creates and persists a new transaction for the given user.
     *
     * @param  array{name: string, description?: string|null, date: string, value: numeric-string, category_id: int}  $data
     */
    public function create(User $user, array $data): Transaction;

    /**
     * Updates the given transaction with the provided data.
     *
     * @param  array{name: string, description?: string|null, date: string, value: numeric-string, category_id: int}  $data
     */
    public function update(Transaction $transaction, array $data): Transaction;

    /**
     * Deletes the given transaction.
     */
    public function delete(Transaction $transaction): void;

    /**
     * Updates multiple transactions in a single database transaction.
     * If any authorization or update fails, all changes are rolled back.
     *
     * @param  array<int, array{id: int, name: string, description?: string|null, date: string, value: numeric-string, category_id: int}>  $transactionsData
     * @return Collection<int, Transaction>
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function bulkUpdate(User $user, array $transactionsData): Collection;
}
