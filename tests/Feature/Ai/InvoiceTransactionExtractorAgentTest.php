<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\InvoiceTransactionExtractorAgent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceTransactionExtractorAgentTest extends TestCase
{
    public function test_it_extracts_transactions_from_invoice_text(): void
    {
        InvoiceTransactionExtractorAgent::fake([
            [
                'transactions' => [
                    ['value' => 150.00, 'description' => 'Web Hosting Service', 'date' => '2024-01-15'],
                    ['value' => 49.90, 'description' => 'Domain Registration', 'date' => '2024-01-15'],
                ],
            ],
        ]);

        $invoiceContent = <<<'INVOICE'
        Invoice #001 - Date: 2024-01-15
        Web Hosting Service .... R$ 150,00
        Domain Registration ..... R$ 49,90
        Total: R$ 199,90
        INVOICE;

        $response = (new InvoiceTransactionExtractorAgent)->prompt($invoiceContent);

        $this->assertCount(2, $response['transactions']);

        $this->assertEquals(150.00, $response['transactions'][0]['value']);
        $this->assertEquals('Web Hosting Service', $response['transactions'][0]['description']);
        $this->assertEquals('2024-01-15', $response['transactions'][0]['date']);

        $this->assertEquals(49.90, $response['transactions'][1]['value']);
        $this->assertEquals('Domain Registration', $response['transactions'][1]['description']);
        $this->assertEquals('2024-01-15', $response['transactions'][1]['date']);
    }

    public function test_it_returns_empty_transactions_for_invoice_without_line_items(): void
    {
        InvoiceTransactionExtractorAgent::fake([
            ['transactions' => []],
        ]);

        $response = (new InvoiceTransactionExtractorAgent)->prompt('Invoice with no items.');

        $this->assertEmpty($response['transactions']);
    }

    public function test_it_handles_credit_transactions_with_negative_values(): void
    {
        InvoiceTransactionExtractorAgent::fake([
            [
                'transactions' => [
                    ['value' => 200.00, 'description' => 'Monthly Plan', 'date' => '2024-02-01'],
                    ['value' => -50.00, 'description' => 'Loyalty Discount', 'date' => '2024-02-01'],
                ],
            ],
        ]);

        $response = (new InvoiceTransactionExtractorAgent)->prompt('Invoice with a discount applied.');

        $this->assertCount(2, $response['transactions']);
        $this->assertLessThan(0, $response['transactions'][1]['value']);
    }

    public function test_it_prompts_with_the_provided_invoice_content(): void
    {
        InvoiceTransactionExtractorAgent::fake([
            ['transactions' => []],
        ]);

        $invoiceContent = 'Invoice #999 - Service Fee: R$ 300,00';

        (new InvoiceTransactionExtractorAgent)->prompt($invoiceContent);

        InvoiceTransactionExtractorAgent::assertPrompted($invoiceContent);
    }

    public function test_it_extracts_transactions_from_an_uploaded_pdf(): void
    {
        InvoiceTransactionExtractorAgent::fake([
            [
                'transactions' => [
                    ['value' => 320.00, 'description' => 'Consulting Service', 'date' => '2024-03-10'],
                ],
            ],
        ]);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = (new InvoiceTransactionExtractorAgent)->extract($pdf);

        $this->assertCount(1, $response['transactions']);
        $this->assertEquals(320.00, $response['transactions'][0]['value']);
        $this->assertEquals('Consulting Service', $response['transactions'][0]['description']);
        $this->assertEquals('2024-03-10', $response['transactions'][0]['date']);
    }

    public function test_it_extracts_transactions_from_a_stored_pdf(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('invoices/invoice.pdf', '%PDF-1.4 fake content');

        InvoiceTransactionExtractorAgent::fake([
            [
                'transactions' => [
                    ['value' => 89.90, 'description' => 'Annual Subscription', 'date' => '2024-04-01'],
                ],
            ],
        ]);

        $response = (new InvoiceTransactionExtractorAgent)->extract('invoices/invoice.pdf', 'local');

        $this->assertCount(1, $response['transactions']);
        $this->assertEquals(89.90, $response['transactions'][0]['value']);
    }

    public function test_it_extracts_transactions_from_a_local_path_pdf(): void
    {
        InvoiceTransactionExtractorAgent::fake([
            [
                'transactions' => [
                    ['value' => 500.00, 'description' => 'Project Setup Fee', 'date' => '2024-05-20'],
                ],
            ],
        ]);

        $response = (new InvoiceTransactionExtractorAgent)->extract('/tmp/invoice.pdf');

        $this->assertCount(1, $response['transactions']);
        $this->assertEquals(500.00, $response['transactions'][0]['value']);
    }
}
