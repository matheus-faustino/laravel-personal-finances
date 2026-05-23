<?php

namespace Tests\Feature\Document;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Bus::fake();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Document',
            'description' => 'A test description',
            'file' => UploadedFile::fake()->create('document.pdf', 512, 'application/pdf'),
        ], $overrides);
    }

    // --- index ---

    public function test_admin_can_list_all_documents(): void
    {
        $admin = User::factory()->admin()->create();
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        Document::factory()->count(2)->create(['user_id' => $clientA->id]);
        Document::factory()->count(3)->create(['user_id' => $clientB->id]);

        $this->actingAs($admin)->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_client_can_only_list_their_own_documents(): void
    {
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        Document::factory()->count(3)->create(['user_id' => $clientA->id]);
        Document::factory()->count(2)->create(['user_id' => $clientB->id]);

        $this->actingAs($clientA)->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_unauthenticated_user_cannot_list_documents(): void
    {
        $this->getJson('/api/documents')->assertUnauthorized();
    }

    // --- show ---

    public function test_admin_can_show_any_document(): void
    {
        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create(['name' => 'Admin View']);

        $this->actingAs($admin)->getJson("/api/documents/{$document->id}")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Admin View']);
    }

    public function test_client_can_show_their_own_document(): void
    {
        $client = User::factory()->client()->create();
        $document = Document::factory()->create(['user_id' => $client->id, 'name' => 'My Doc']);

        $this->actingAs($client)->getJson("/api/documents/{$document->id}")
            ->assertOk()
            ->assertJsonFragment(['name' => 'My Doc']);
    }

    public function test_client_cannot_show_another_clients_document(): void
    {
        $client = User::factory()->client()->create();
        $other = Document::factory()->create();

        $this->actingAs($client)->getJson("/api/documents/{$other->id}")->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_show_a_document(): void
    {
        $document = Document::factory()->create();

        $this->getJson("/api/documents/{$document->id}")->assertUnauthorized();
    }

    public function test_show_returns_404_for_unknown_document(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/documents/999')->assertNotFound();
    }

    // --- store ---

    public function test_client_can_create_a_document(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->postJson('/api/documents', $this->validPayload())
            ->assertCreated();

        $document = Document::first();
        $this->assertDatabaseHas('documents', ['name' => 'Test Document', 'user_id' => $client->id, 'status' => DocumentStatus::Uploaded->value]);
        Storage::disk('local')->assertExists($document->file);
    }

    public function test_user_id_is_always_assigned_from_authenticated_user(): void
    {
        $client = User::factory()->client()->create();
        $other = User::factory()->client()->create();

        $this->actingAs($client)->postJson('/api/documents', $this->validPayload(['user_id' => $other->id]))
            ->assertCreated();

        $this->assertDatabaseHas('documents', ['name' => 'Test Document', 'user_id' => $client->id]);
        $this->assertDatabaseMissing('documents', ['name' => 'Test Document', 'user_id' => $other->id]);
    }

    public function test_admin_cannot_create_a_document(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/documents', $this->validPayload())
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_a_document(): void
    {
        $this->postJson('/api/documents', $this->validPayload())->assertUnauthorized();
    }

    public function test_store_requires_name(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->postJson('/api/documents', $this->validPayload(['name' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_file(): void
    {
        $client = User::factory()->client()->create();
        $payload = ['name' => 'Test Document', 'description' => 'A test description'];

        $this->actingAs($client)->postJson('/api/documents', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_store_rejects_file_exceeding_10mb(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->postJson('/api/documents', $this->validPayload([
            'file' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['file']);
    }

    public function test_store_accepts_nullable_description(): void
    {
        $client = User::factory()->client()->create();
        $payload = $this->validPayload(['description' => null]);

        $this->actingAs($client)->postJson('/api/documents', $payload)->assertCreated();

        $this->assertDatabaseHas('documents', ['name' => 'Test Document', 'description' => null]);
    }

    // --- update ---

    public function test_client_can_update_their_own_document_without_new_file(): void
    {
        $client = User::factory()->client()->create();
        $document = Document::factory()->create(['user_id' => $client->id, 'name' => 'Old Name']);
        $originalFile = $document->file;

        $this->actingAs($client)->putJson("/api/documents/{$document->id}", [
            'name' => 'New Name',
            'description' => 'Updated description',
        ])->assertOk()->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'name' => 'New Name']);
        $this->assertSame($originalFile, $document->fresh()->file);
    }

    public function test_client_can_update_their_own_document_with_new_file(): void
    {
        $client = User::factory()->client()->create();
        Storage::disk('local')->put("documents/{$client->id}/old.pdf", 'old content');
        $document = Document::factory()->create([
            'user_id' => $client->id,
            'file' => "documents/{$client->id}/old.pdf",
        ]);
        $oldPath = $document->file;

        $this->actingAs($client)->putJson("/api/documents/{$document->id}", [
            'name' => 'Updated',
            'file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ])->assertOk();

        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($document->fresh()->file);
    }

    public function test_client_cannot_update_another_clients_document(): void
    {
        $clientA = User::factory()->client()->create();
        $document = Document::factory()->create(['name' => 'Original']);

        $this->actingAs($clientA)->putJson("/api/documents/{$document->id}", [
            'name' => 'Hacked',
        ])->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'name' => 'Original']);
    }

    public function test_admin_cannot_update_a_document(): void
    {
        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create(['name' => 'Original']);

        $this->actingAs($admin)->putJson("/api/documents/{$document->id}", [
            'name' => 'Changed',
        ])->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'name' => 'Original']);
    }

    public function test_unauthenticated_user_cannot_update_a_document(): void
    {
        $document = Document::factory()->create();

        $this->putJson("/api/documents/{$document->id}", ['name' => 'X'])->assertUnauthorized();
    }

    public function test_update_requires_name(): void
    {
        $client = User::factory()->client()->create();
        $document = Document::factory()->create(['user_id' => $client->id]);

        $this->actingAs($client)->putJson("/api/documents/{$document->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_rejects_file_exceeding_10mb_when_provided(): void
    {
        $client = User::factory()->client()->create();
        $document = Document::factory()->create(['user_id' => $client->id]);

        $this->actingAs($client)->putJson("/api/documents/{$document->id}", [
            'name' => 'Test',
            'file' => UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['file']);
    }

    // --- destroy ---

    public function test_client_can_delete_their_own_document(): void
    {
        $client = User::factory()->client()->create();
        Storage::disk('local')->put("documents/{$client->id}/file.pdf", 'content');
        $document = Document::factory()->create([
            'user_id' => $client->id,
            'file' => "documents/{$client->id}/file.pdf",
        ]);
        $filePath = $document->file;

        $this->actingAs($client)->deleteJson("/api/documents/{$document->id}")->assertNoContent();

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($filePath);
    }

    public function test_client_cannot_delete_another_clients_document(): void
    {
        $clientA = User::factory()->client()->create();
        $document = Document::factory()->create();

        $this->actingAs($clientA)->deleteJson("/api/documents/{$document->id}")->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_admin_cannot_delete_a_document(): void
    {
        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/documents/{$document->id}")->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_unauthenticated_user_cannot_delete_a_document(): void
    {
        $document = Document::factory()->create();

        $this->deleteJson("/api/documents/{$document->id}")->assertUnauthorized();
    }

    // --- download ---

    public function test_client_can_download_their_own_document(): void
    {
        $client = User::factory()->client()->create();
        Storage::disk('local')->put("documents/{$client->id}/file.pdf", 'dummy content');
        $document = Document::factory()->create([
            'user_id' => $client->id,
            'file' => "documents/{$client->id}/file.pdf",
        ]);

        $this->actingAs($client)->getJson("/api/documents/{$document->id}/download")
            ->assertOk()
            ->assertHeader('Content-Disposition');
    }

    public function test_admin_can_download_any_document(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        Storage::disk('local')->put("documents/{$client->id}/file.pdf", 'dummy content');
        $document = Document::factory()->create([
            'user_id' => $client->id,
            'file' => "documents/{$client->id}/file.pdf",
        ]);

        $this->actingAs($admin)->getJson("/api/documents/{$document->id}/download")
            ->assertOk()
            ->assertHeader('Content-Disposition');
    }

    public function test_client_cannot_download_another_clients_document(): void
    {
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();
        Storage::disk('local')->put("documents/{$clientB->id}/file.pdf", 'dummy content');
        $document = Document::factory()->create([
            'user_id' => $clientB->id,
            'file' => "documents/{$clientB->id}/file.pdf",
        ]);

        $this->actingAs($clientA)->getJson("/api/documents/{$document->id}/download")->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_download_a_document(): void
    {
        $document = Document::factory()->create();

        $this->getJson("/api/documents/{$document->id}/download")->assertUnauthorized();
    }
}
