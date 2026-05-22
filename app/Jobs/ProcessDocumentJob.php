<?php

namespace App\Jobs;

use App\Ai\Agents\InvoiceTransactionExtractorAgent;
use App\Enums\DocumentStatus;
use App\Interfaces\DocumentServiceInterface;
use App\Interfaces\TransactionServiceInterface;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDocumentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Document $document) {}

    public function handle(InvoiceTransactionExtractorAgent $agent, DocumentServiceInterface $documentService, TransactionServiceInterface $transactionService): void
    {
        $documentService->update($this->document, ['status' => DocumentStatus::Processing], null);

        try {
            $result = $agent->extract($this->document->file, 'local');

            collect($result['transactions'])->each(fn (array $data) => $transactionService->create($this->document->client, [
                'name' => $data['description'],
                'value' => $data['value'],
                'date' => $data['date'],
                'document_id' => $this->document->id,
            ]));

            $documentService->update($this->document, ['status' => DocumentStatus::Processed], null);
        } catch (\Throwable $e) {
            $documentService->update($this->document, [
                'status' => DocumentStatus::Failed,
                'fail_reason' => 'There was an error while processing this document',
            ], null);

            throw $e;
        }
    }
}
