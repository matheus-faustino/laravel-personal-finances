<?php

namespace App\Http\Controllers;

use App\Http\Requests\Transaction\IndexTransactionRequest;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Interfaces\TransactionServiceInterface;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionServiceInterface $transactionService) {}

    public function index(IndexTransactionRequest $request): AnonymousResourceCollection
    {
        return TransactionResource::collection(
            $this->transactionService->getAllForUser($request->user(), $request->validated())
        );
    }

    public function show(Transaction $transaction): TransactionResource
    {
        Gate::authorize('view-transaction', $transaction);

        return new TransactionResource($transaction);
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        Gate::authorize('create-transaction');

        $transaction = $this->transactionService->create($request->user(), $request->validated());

        return (new TransactionResource($transaction))->response()->setStatusCode(201);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): TransactionResource
    {
        Gate::authorize('modify-transaction', $transaction);

        return new TransactionResource($this->transactionService->update($transaction, $request->validated()));
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        Gate::authorize('modify-transaction', $transaction);

        $this->transactionService->delete($transaction);

        return response()->json(null, 204);
    }
}
