<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Promptable;

class InvoiceTransactionExtractorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You are a financial data extraction assistant specialized in reading invoices and bills.

        Your task is to extract all transactions from the provided invoice content.

        For each transaction found, extract:
        - value: the monetary amount as a number (positive for charges, negative for credits/refunds)
        - description: a clear, concise description of the item or service
        - date: the transaction date in ISO 8601 format (YYYY-MM-DD)

        If a specific date is not available for an individual transaction, use the invoice date.
        Only extract actual transaction line items — ignore totals, taxes, and summaries.
        INSTRUCTIONS;
    }

    /**
     * Extract transactions from a PDF file (uploaded, local path, or stored on disk).
     *
     * @return array{transactions: array<int, array{value: float, description: string, date: string}>}
     */
    public function extract(UploadedFile|string $file, ?string $disk = null): mixed
    {
        $attachment = match (true) {
            $file instanceof UploadedFile => Document::fromUpload($file),
            $disk !== null => Document::fromStorage($file, $disk),
            default => Document::fromPath($file),
        };

        return $this->prompt('Extract all transactions from this invoice PDF.', [$attachment], Lab::Gemini);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'transactions' => $schema->array()
                ->items(
                    $schema->object([
                        'value' => $schema->number()
                            ->description('Monetary amount of the transaction. Positive for charges, negative for credits.')
                            ->required(),
                        'description' => $schema->string()
                            ->description('Description of the item or service.')
                            ->required(),
                        'date' => $schema->string()
                            ->description('Transaction date in YYYY-MM-DD format.')
                            ->required(),
                    ])
                )
                ->description('List of transactions extracted from the invoice.')
                ->required(),
        ];
    }
}
