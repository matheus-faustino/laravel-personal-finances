<?php

namespace App\Http\Controllers;

use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Interfaces\TransactionServiceInterface;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionServiceInterface $transactionService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->transactionService->getAllForUser($request->user()));
    }

    public function show(Transaction $transaction): JsonResponse
    {
        Gate::authorize('view-transaction', $transaction);

        return response()->json($transaction);
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        Gate::authorize('create-transaction');

        $transaction = $this->transactionService->create($request->user(), $request->validated());

        return response()->json($transaction, 201);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        Gate::authorize('modify-transaction', $transaction);

        return response()->json($this->transactionService->update($transaction, $request->validated()));
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        Gate::authorize('modify-transaction', $transaction);

        $this->transactionService->delete($transaction);

        return response()->json(null, 204);
    }
}
