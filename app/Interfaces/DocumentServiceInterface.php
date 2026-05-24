<?php

namespace App\Interfaces;

use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface DocumentServiceInterface
{
    /**
     * Returns a paginated list of documents accessible by the given user,
     * optionally filtered by date range on the `created_at` column.
     *
     * @param  array{start_date?: string|null, end_date?: string|null, per_page?: int|null}  $filters
     */
    public function getAllForUser(User $user, array $filters = []): LengthAwarePaginator;

    /**
     * Creates and persists a new document for the given user, storing the uploaded file.
     *
     * @param  array{name: string, description?: string|null}  $data
     */
    public function create(User $user, array $data, UploadedFile $file): Document;

    /**
     * Updates the given document with the provided data, replacing its file if a new one is given.
     *
     * @param  array{name: string, description?: string|null}  $data
     */
    public function update(Document $document, array $data, ?UploadedFile $file): Document;

    /**
     * Deletes the given document and its associated file from storage.
     */
    public function delete(Document $document): void;
}
