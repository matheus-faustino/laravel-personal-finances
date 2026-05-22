<?php

namespace Tests\Feature\Jobs;

use App\Ai\Agents\TransactionCategorizerAgent;
use App\Interfaces\CategoryServiceInterface;
use App\Interfaces\TransactionServiceInterface;
use App\Jobs\CategorizeTransactionsJob;
use App\Models\Category;
use App\Models\Document;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class CategorizeTransactionsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_calls_agent_with_transactions_and_categories(): void
    {
        /** @var Document $document */
        $document = Document::factory()->create();
        $transactions = Transaction::factory()->count(2)->create(['document_id' => $document->id]);
        $categories = Category::factory()->count(3)->create();

        /** @var TransactionCategorizerAgent&MockInterface $agent */
        $agent = $this->mock(TransactionCategorizerAgent::class);
        $agent->shouldReceive('categorize')
            ->once()
            ->andReturnUsing(function (array $transactionsData, $categoriesArg) use ($categories): array {
                $this->assertCount(2, $transactionsData);
                $this->assertCount($categories->count(), $categoriesArg);

                return [];
            });

        /** @var CategoryServiceInterface&MockInterface $categoryService */
        $categoryService = $this->mock(CategoryServiceInterface::class);
        $categoryService->shouldReceive('getAll')
            ->once()
            ->andReturn($categories);

        /** @var TransactionServiceInterface&MockInterface $transactionService */
        $transactionService = $this->mock(TransactionServiceInterface::class);
        $transactionService->shouldReceive('getAllForDocument')
            ->once()
            ->with($document)
            ->andReturn($transactions);
        $transactionService->shouldNotReceive('update');

        (new CategorizeTransactionsJob($document))->handle($agent, $categoryService, $transactionService);
    }

    public function test_job_updates_transactions_with_assigned_category_ids(): void
    {
        $category = Category::factory()->create();
        /** @var Document $document */
        $document = Document::factory()->create();
        $transactions = Transaction::factory()->count(2)->create([
            'document_id' => $document->id,
            'category_id' => null,
        ]);

        /** @var TransactionCategorizerAgent&MockInterface $agent */
        $agent = $this->mock(TransactionCategorizerAgent::class);
        $agent->shouldReceive('categorize')
            ->andReturn([
                array_merge($transactions[0]->toArray(), ['category_id' => $category->id]),
                array_merge($transactions[1]->toArray(), ['category_id' => $category->id]),
            ]);

        /** @var CategoryServiceInterface&MockInterface $categoryService */
        $categoryService = $this->mock(CategoryServiceInterface::class);
        $categoryService->shouldReceive('getAll')
            ->andReturn(new Collection([$category]));

        /** @var TransactionServiceInterface&MockInterface $transactionService */
        $transactionService = $this->mock(TransactionServiceInterface::class);
        $transactionService->shouldReceive('getAllForDocument')
            ->with($document)
            ->andReturn($transactions);
        $transactionService->shouldReceive('get')
            ->once()
            ->with($transactions[0]->id)
            ->andReturn($transactions[0]);
        $transactionService->shouldReceive('get')
            ->once()
            ->with($transactions[1]->id)
            ->andReturn($transactions[1]);
        $transactionService->shouldReceive('update')
            ->once()
            ->with($transactions[0], ['category_id' => $category->id])
            ->andReturn($transactions[0]);
        $transactionService->shouldReceive('update')
            ->once()
            ->with($transactions[1], ['category_id' => $category->id])
            ->andReturn($transactions[1]);

        (new CategorizeTransactionsJob($document))->handle($agent, $categoryService, $transactionService);
    }

    public function test_job_sets_null_category_id_for_unmatched_transactions(): void
    {
        $category = Category::factory()->create();
        /** @var Document $document */
        $document = Document::factory()->create();
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()->create([
            'document_id' => $document->id,
            'category_id' => $category->id,
        ]);

        /** @var TransactionCategorizerAgent&MockInterface $agent */
        $agent = $this->mock(TransactionCategorizerAgent::class);
        $agent->shouldReceive('categorize')
            ->andReturn([
                array_merge($transaction->toArray(), ['category_id' => null]),
            ]);

        /** @var CategoryServiceInterface&MockInterface $categoryService */
        $categoryService = $this->mock(CategoryServiceInterface::class);
        $categoryService->shouldReceive('getAll')
            ->andReturn(new Collection);

        /** @var TransactionServiceInterface&MockInterface $transactionService */
        $transactionService = $this->mock(TransactionServiceInterface::class);
        $transactionService->shouldReceive('getAllForDocument')
            ->with($document)
            ->andReturn(new Collection([$transaction]));
        $transactionService->shouldReceive('get')
            ->once()
            ->with($transaction->id)
            ->andReturn($transaction);
        $transactionService->shouldReceive('update')
            ->once()
            ->with($transaction, ['category_id' => null])
            ->andReturn($transaction);

        (new CategorizeTransactionsJob($document))->handle($agent, $categoryService, $transactionService);
    }

    public function test_job_skips_agent_call_when_document_has_no_transactions(): void
    {
        /** @var Document $document */
        $document = Document::factory()->create();

        /** @var TransactionCategorizerAgent&MockInterface $agent */
        $agent = $this->mock(TransactionCategorizerAgent::class);
        $agent->shouldNotReceive('categorize');

        /** @var CategoryServiceInterface&MockInterface $categoryService */
        $categoryService = $this->mock(CategoryServiceInterface::class);
        $categoryService->shouldNotReceive('getAll');

        /** @var TransactionServiceInterface&MockInterface $transactionService */
        $transactionService = $this->mock(TransactionServiceInterface::class);
        $transactionService->shouldReceive('getAllForDocument')
            ->with($document)
            ->andReturn(new Collection);
        $transactionService->shouldNotReceive('update');

        (new CategorizeTransactionsJob($document))->handle($agent, $categoryService, $transactionService);
    }

    public function test_job_logs_error_and_rethrows_when_agent_throws(): void
    {
        /** @var Document $document */
        $document = Document::factory()->create();
        $transactions = Transaction::factory()->count(1)->create(['document_id' => $document->id]);

        /** @var TransactionCategorizerAgent&MockInterface $agent */
        $agent = $this->mock(TransactionCategorizerAgent::class);
        $agent->shouldReceive('categorize')
            ->andThrow(new RuntimeException('Categorizer failed'));

        /** @var CategoryServiceInterface&MockInterface $categoryService */
        $categoryService = $this->mock(CategoryServiceInterface::class);
        $categoryService->shouldReceive('getAll')
            ->andReturn(new Collection);

        /** @var TransactionServiceInterface&MockInterface $transactionService */
        $transactionService = $this->mock(TransactionServiceInterface::class);
        $transactionService->shouldReceive('getAllForDocument')
            ->with($document)
            ->andReturn($transactions);
        $transactionService->shouldNotReceive('update');

        Log::shouldReceive('error')
            ->once()
            ->with('CategorizeTransactionsJob failed', \Mockery::subset([
                'document_id' => $document->id,
                'error' => 'Categorizer failed',
            ]));

        try {
            (new CategorizeTransactionsJob($document))->handle($agent, $categoryService, $transactionService);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Categorizer failed', $e->getMessage());
        }
    }
}
