<?php

namespace App\Services;

use App\Interfaces\DocumentServiceInterface;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
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

        return Document::create($data);
    }

    public function update(Document $document, array $data, ?UploadedFile $file): Document
    {
        if ($file !== null) {
            Storage::disk('local')->delete($document->file);
            $data['file'] = $file->store("documents/{$document->user_id}", 'local');
        }

        $document->update($data);

        return $document;
    }

    public function delete(Document $document): void
    {
        Storage::disk('local')->delete($document->file);
        $document->delete();
    }
}
