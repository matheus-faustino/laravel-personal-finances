<?php

namespace Tests\Unit\Document;

use App\Interfaces\DocumentServiceInterface;
use App\Jobs\CategorizeTransactionsJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Bus::fake();

        $this->service = $this->app->make(DocumentServiceInterface::class);
    }

    public function test_get_all_for_user_returns_all_documents_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $clientA = User::factory()->user()->create();
        $clientB = User::factory()->user()->create();

        Document::factory()->count(2)->create(['user_id' => $clientA->id]);
        Document::factory()->count(3)->create(['user_id' => $clientB->id]);

        $result = $this->service->getAllForUser($admin);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(5, $result);
    }

    public function test_get_all_for_user_returns_only_own_documents_for_client(): void
    {
        $clientA = User::factory()->user()->create();
        $clientB = User::factory()->user()->create();

        Document::factory()->count(3)->create(['user_id' => $clientA->id]);
        Document::factory()->count(2)->create(['user_id' => $clientB->id]);

        $result = $this->service->getAllForUser($clientA);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(3, $result);
    }

    public function test_get_all_for_user_returns_empty_collection_when_no_documents_exist(): void
    {
        $admin = User::factory()->admin()->create();

        $result = $this->service->getAllForUser($admin);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(0, $result);
    }

    public function test_get_all_for_user_filters_by_start_date(): void
    {
        $admin = User::factory()->admin()->create();

        Document::factory()->create(['created_at' => '2026-01-15']);
        Document::factory()->create(['created_at' => '2026-03-10']);

        $result = $this->service->getAllForUser($admin, ['start_date' => '2026-02-01']);

        $this->assertSame(1, $result->total());
    }

    public function test_get_all_for_user_filters_by_end_date(): void
    {
        $admin = User::factory()->admin()->create();

        Document::factory()->create(['created_at' => '2026-01-15']);
        Document::factory()->create(['created_at' => '2026-03-10']);

        $result = $this->service->getAllForUser($admin, ['end_date' => '2026-02-28']);

        $this->assertSame(1, $result->total());
    }

    public function test_get_all_for_user_filters_by_date_range(): void
    {
        $admin = User::factory()->admin()->create();

        Document::factory()->create(['created_at' => '2025-12-01']);
        Document::factory()->create(['created_at' => '2026-01-15']);
        Document::factory()->create(['created_at' => '2026-03-10']);

        $result = $this->service->getAllForUser($admin, ['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);

        $this->assertSame(1, $result->total());
    }

    public function test_get_all_for_user_filters_by_user_id_when_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $clientA = User::factory()->user()->create();
        $clientB = User::factory()->user()->create();

        Document::factory()->count(3)->create(['user_id' => $clientA->id]);
        Document::factory()->count(2)->create(['user_id' => $clientB->id]);

        $result = $this->service->getAllForUser($admin, ['user_id' => $clientA->id]);

        $this->assertSame(3, $result->total());
    }

    public function test_get_all_for_user_ignores_user_id_filter_for_non_admin(): void
    {
        $clientA = User::factory()->user()->create();
        $clientB = User::factory()->user()->create();

        Document::factory()->count(3)->create(['user_id' => $clientA->id]);
        Document::factory()->count(2)->create(['user_id' => $clientB->id]);

        $result = $this->service->getAllForUser($clientA, ['user_id' => $clientB->id]);

        $this->assertSame(3, $result->total());
    }

    public function test_get_all_for_user_paginates_results(): void
    {
        $client = User::factory()->user()->create();
        Document::factory()->count(20)->create(['user_id' => $client->id]);

        $result = $this->service->getAllForUser($client, ['per_page' => 5]);

        $this->assertCount(5, $result);
        $this->assertSame(20, $result->total());
    }

    public function test_get_all_for_user_uses_default_per_page_of_15(): void
    {
        $admin = User::factory()->admin()->create();
        Document::factory()->count(20)->create();

        $result = $this->service->getAllForUser($admin);

        $this->assertCount(15, $result);
        $this->assertSame(20, $result->total());
    }

    public function test_create_stores_file_and_persists_document(): void
    {
        $client = User::factory()->user()->create();
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $result = $this->service->create($client, ['name' => 'My Doc'], $file);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertSame($client->id, $result->user_id);
        $this->assertDatabaseHas('documents', ['name' => 'My Doc', 'user_id' => $client->id]);
        Storage::disk('local')->assertExists($result->file);
    }

    public function test_create_forces_user_id_to_authenticated_user(): void
    {
        $client = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $result = $this->service->create($client, ['name' => 'Spoofed', 'user_id' => $other->id], $file);

        $this->assertSame($client->id, $result->user_id);
        $this->assertDatabaseMissing('documents', ['name' => 'Spoofed', 'user_id' => $other->id]);
    }

    public function test_create_stores_file_under_correct_directory(): void
    {
        $client = User::factory()->user()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $result = $this->service->create($client, ['name' => 'Dir Test'], $file);

        $this->assertStringStartsWith("documents/{$client->id}/", $result->file);
    }

    public function test_update_changes_document_fields_without_new_file(): void
    {
        $document = Document::factory()->create(['name' => 'Old Name']);
        $originalFile = $document->file;

        $this->service->update($document, ['name' => 'New Name', 'description' => 'Desc'], null);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'name' => 'New Name']);
        $this->assertSame($originalFile, $document->fresh()->file);
    }

    public function test_update_replaces_file_when_new_file_provided(): void
    {
        $client = User::factory()->user()->create();
        Storage::disk('local')->put("documents/{$client->id}/old.pdf", 'old content');
        $document = Document::factory()->create([
            'user_id' => $client->id,
            'file' => "documents/{$client->id}/old.pdf",
        ]);
        $oldPath = $document->file;
        $newFile = UploadedFile::fake()->create('new.pdf', 100, 'application/pdf');

        $this->service->update($document, ['name' => 'Updated'], $newFile);

        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($document->fresh()->file);
    }

    public function test_update_returns_the_updated_document(): void
    {
        $document = Document::factory()->create();

        $result = $this->service->update($document, ['name' => 'Updated'], null);

        $this->assertTrue($result->is($document));
    }

    public function test_delete_removes_document_record_and_file(): void
    {
        $client = User::factory()->user()->create();
        Storage::disk('local')->put("documents/{$client->id}/file.pdf", 'content');
        $document = Document::factory()->create([
            'user_id' => $client->id,
            'file' => "documents/{$client->id}/file.pdf",
        ]);
        $path = $document->file;

        $this->service->delete($document);

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_create_dispatches_process_and_categorize_job_chain(): void
    {
        $client = User::factory()->user()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->service->create($client, ['name' => 'My Doc'], $file);

        Bus::assertChained([ProcessDocumentJob::class, CategorizeTransactionsJob::class]);
    }
}
