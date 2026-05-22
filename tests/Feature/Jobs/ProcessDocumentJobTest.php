<?php

namespace Tests\Feature\Jobs;

use App\Ai\Agents\InvoiceTransactionExtractorAgent;
use App\Enums\DocumentStatus;
use App\Interfaces\DocumentServiceInterface;
use App\Interfaces\TransactionServiceInterface;
use App\Jobs\CategorizeTransactionsJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ProcessDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_job_is_dispatched_when_document_is_created(): void
    {
        Bus::fake();

        $client = User::factory()->client()->create();

        $this->actingAs($client)->postJson('/api/documents', [
            'name' => 'Test Invoice',
            'file' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        Bus::assertChained([ProcessDocumentJob::class, CategorizeTransactionsJob::class]);
    }

    public function test_job_sets_document_status_to_processing_then_processed(): void
    {
        $document = Document::factory()->create(['status' => DocumentStatus::Uploaded]);

        /** @var InvoiceTransactionExtractorAgent&MockInterface $agent */
        $agent = $this->mock(InvoiceTransactionExtractorAgent::class);
        $agent->shouldReceive('extract')
            ->andReturnUsing(function () use ($document): array {
                $this->assertDatabaseHas('documents', [
                    'id' => $document->id,
                    'status' => DocumentStatus::Processing->value,
                ]);

                return ['transactions' => []];
            });

        /** @var DocumentServiceInterface&MockInterface $documentService */
        $documentService = $this->mock(DocumentServiceInterface::class);
        $documentService->shouldReceive('update')
            ->andReturnUsing(function (Document $doc, array $data, mixed $file) {
                $doc->update($data);

                return $doc;
            });

        /** @var TransactionServiceInterface&MockInterface $transactionService */
        $transactionService = $this->mock(TransactionServiceInterface::class);

        (new ProcessDocumentJob($document))->handle($agent, $documentService, $transactionService);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => DocumentStatus::Processed->value,
        ]);
    }

    public function test_job_calls_agent_with_document_file_on_local_disk(): void
    {
        $document = Document::factory()->create(['file' => 'documents/42/abc.pdf']);

        /** @var InvoiceTransactionExtractorAgent&MockInterface $agent */
        $agent = $this->mock(InvoiceTransactionExtractorAgent::class);
        $agent->shouldReceive('extract')
            ->once()
            ->with($document->file, 'local')
            ->andReturn(['transactions' => []]);

        /** @var DocumentServiceInterface&MockInterface $documentService */
        $documentService = $this->mock(DocumentServiceInterface::class);
        $documentService->shouldReceive('update');

        /** @var TransactionServiceInterface&MockInterface $transactionService */
        $transactionService = $this->mock(TransactionServiceInterface::class);

        (new ProcessDocumentJob($document))->handle($agent, $documentService, $transactionService);
    }

    public function test_job_creates_transactions_from_extracted_data(): void
    {
        $document = Document::factory()->create();

        /** @var InvoiceTransactionExtractorAgent&MockInterface $agent */
        $agent = $this->mock(InvoiceTransactionExtractorAgent::class);
        $agent->shouldReceive('extract')
            ->andReturn([
                'transactions' => [
                    ['description' => 'Internet service', 'value' => 99.90, 'date' => '2026-05-01'],
                    ['description' => 'Cloud storage', 'value' => 29.90, 'date' => '2026-05-05'],
                ],
            ]);

        /** @var DocumentServiceInterface&MockInterface $documentService */
        $documentService = $this->mock(DocumentServiceInterface::class);
        $documentService->shouldReceive('update');

        /** @var TransactionServiceInterface&MockInterface $transactionService */
        $transactionService = $this->mock(TransactionServiceInterface::class);
        $transactionService->shouldReceive('create')
            ->once()
            ->with($document->client, [
                'name' => 'Internet service',
                'value' => 99.90,
                'date' => '2026-05-01',
                'document_id' => $document->id,
            ]);
        $transactionService->shouldReceive('create')
            ->once()
            ->with($document->client, [
                'name' => 'Cloud storage',
                'value' => 29.90,
                'date' => '2026-05-05',
                'document_id' => $document->id,
            ]);

        (new ProcessDocumentJob($document))->handle($agent, $documentService, $transactionService);
    }

    public function test_job_does_not_create_transactions_when_none_extracted(): void
    {
        $document = Document::factory()->create();

        /** @var InvoiceTransactionExtractorAgent&MockInterface $agent */
        $agent = $this->mock(InvoiceTransactionExtractorAgent::class);
        $agent->shouldReceive('extract')
            ->andReturn(['transactions' => []]);

        /** @var DocumentServiceInterface&MockInterface $documentService */
        $documentService = $this->mock(DocumentServiceInterface::class);
        $documentService->shouldReceive('update');

        /** @var TransactionServiceInterface&MockInterface $transactionService */
        $transactionService = $this->mock(TransactionServiceInterface::class);
        $transactionService->shouldNotReceive('create');

        (new ProcessDocumentJob($document))->handle($agent, $documentService, $transactionService);
    }

    public function test_job_sets_document_status_to_failed_when_agent_throws(): void
    {
        $document = Document::factory()->create(['status' => DocumentStatus::Uploaded]);

        /** @var InvoiceTransactionExtractorAgent&MockInterface $agent */
        $agent = $this->mock(InvoiceTransactionExtractorAgent::class);
        $agent->shouldReceive('extract')
            ->andThrow(new RuntimeException('AI service unavailable'));

        /** @var DocumentServiceInterface&MockInterface $documentService */
        $documentService = $this->mock(DocumentServiceInterface::class);
        $documentService->shouldReceive('update')
            ->andReturnUsing(function (Document $doc, array $data, mixed $file) {
                $doc->update($data);

                return $doc;
            });

        /** @var TransactionServiceInterface&MockInterface $transactionService */
        $transactionService = $this->mock(TransactionServiceInterface::class);
        $transactionService->shouldNotReceive('create');

        try {
            (new ProcessDocumentJob($document))->handle($agent, $documentService, $transactionService);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('AI service unavailable', $e->getMessage());
        }

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => DocumentStatus::Failed->value,
        ]);
    }
}
