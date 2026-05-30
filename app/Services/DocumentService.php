<?php

namespace App\Services;

use App\Interfaces\DocumentServiceInterface;
use App\Jobs\CategorizeTransactionsJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class DocumentService implements DocumentServiceInterface
{
    /** {@inheritDoc} */
    public function getAllForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $user->isAdmin()
            ? Document::query()
            : Document::where('user_id', $user->id);

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if ($user->isAdmin() && ! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        return $query->paginate(isset($filters['per_page']) ? (int) $filters['per_page'] : 15);
    }

    /** {@inheritDoc} */
    public function create(User $user, array $data, UploadedFile $file): Document
    {
        $data['user_id'] = $user->id;
        $data['file'] = $file->store("documents/{$user->id}", 'local');

        $document = Document::create($data);

        Bus::chain([
            new ProcessDocumentJob($document),
            new CategorizeTransactionsJob($document),
        ])->dispatch();

        return $document;
    }

    /** {@inheritDoc} */
    public function update(Document $document, array $data, ?UploadedFile $file): Document
    {
        if ($file !== null) {
            if ($document->file) {
                Storage::disk('local')->delete($document->file);
            }
            $data['file'] = $file->store("documents/{$document->user_id}", 'local');
        }

        $document->update($data);

        return $document;
    }

    /** {@inheritDoc} */
    public function delete(Document $document): void
    {
        if ($document->file) {
            Storage::disk('local')->delete($document->file);
        }
        $document->delete();
    }
}
