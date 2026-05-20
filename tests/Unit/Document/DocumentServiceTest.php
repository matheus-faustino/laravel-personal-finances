<?php

namespace Tests\Unit\Document;

use App\Interfaces\DocumentServiceInterface;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        $this->service = $this->app->make(DocumentServiceInterface::class);
    }

    public function test_get_all_for_user_returns_all_documents_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        Document::factory()->count(2)->create(['user_id' => $clientA->id]);
        Document::factory()->count(3)->create(['user_id' => $clientB->id]);

        $result = $this->service->getAllForUser($admin);

        $this->assertCount(5, $result);
    }

    public function test_get_all_for_user_returns_only_own_documents_for_client(): void
    {
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        Document::factory()->count(3)->create(['user_id' => $clientA->id]);
        Document::factory()->count(2)->create(['user_id' => $clientB->id]);

        $result = $this->service->getAllForUser($clientA);

        $this->assertCount(3, $result);
    }

    public function test_get_all_for_user_returns_empty_collection_when_no_documents_exist(): void
    {
        $admin = User::factory()->admin()->create();

        $result = $this->service->getAllForUser($admin);

        $this->assertCount(0, $result);
    }

    public function test_create_stores_file_and_persists_document(): void
    {
        $client = User::factory()->client()->create();
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $result = $this->service->create($client, ['name' => 'My Doc'], $file);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertSame($client->id, $result->user_id);
        $this->assertDatabaseHas('documents', ['name' => 'My Doc', 'user_id' => $client->id]);
        Storage::disk('local')->assertExists($result->file);
    }

    public function test_create_forces_user_id_to_authenticated_user(): void
    {
        $client = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $result = $this->service->create($client, ['name' => 'Spoofed', 'user_id' => $other->id], $file);

        $this->assertSame($client->id, $result->user_id);
        $this->assertDatabaseMissing('documents', ['name' => 'Spoofed', 'user_id' => $other->id]);
    }

    public function test_create_stores_file_under_correct_directory(): void
    {
        $client = User::factory()->client()->create();
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
        $client = User::factory()->client()->create();
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
        $client = User::factory()->client()->create();
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
}
