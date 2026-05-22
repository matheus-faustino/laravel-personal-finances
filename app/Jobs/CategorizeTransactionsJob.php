<?php

namespace App\Jobs;

use App\Ai\Agents\TransactionCategorizerAgent;
use App\Enums\DocumentStatus;
use App\Interfaces\CategoryServiceInterface;
use App\Interfaces\DocumentServiceInterface;
use App\Interfaces\TransactionServiceInterface;
use App\Models\Document;
use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CategorizeTransactionsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Document $document) {}

    public function handle(TransactionCategorizerAgent $agent, CategoryServiceInterface $categoryService, DocumentServiceInterface $documentService, TransactionServiceInterface $transactionService): void
    {
        $transactions = $transactionService->getAllForDocument($this->document);

        if ($transactions->isEmpty()) {
            return;
        }

        $categories = $categoryService->getAll();
        $transactionsData = $transactions->map(fn (Transaction $transaction) => $transaction->toArray())->toArray();

        try {
            $categorized = $agent->categorize($transactionsData, $categories);

            foreach ($categorized as $data) {
                $transaction = $transactionService->get($data['id']);

                $transactionService->update($transaction, ['category_id' => $data['category_id'] ?? null]);
            }
        } catch (\Throwable $e) {
            $documentService->update($this->document, [
                'status' => DocumentStatus::Failed,
                'fail_reason' => 'There was an error while categorizing this document transactions',
            ], null);

            throw $e;
        }
    }
}
