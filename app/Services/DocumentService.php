<?php

namespace App\Services;

use App\Interfaces\DocumentServiceInterface;
use App\Jobs\CategorizeTransactionsJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class DocumentService implements DocumentServiceInterface
{
    public function getAllForUser(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Document::all();
        }

        return Document::where('user_id', $user->id)->get();
    }

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

    public function delete(Document $document): void
    {
        if ($document->file) {
            Storage::disk('local')->delete($document->file);
        }
        $document->delete();
    }
}
