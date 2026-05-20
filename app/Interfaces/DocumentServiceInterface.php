<?php

namespace App\Interfaces;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

interface DocumentServiceInterface
{
    public function getAllForUser(User $user): Collection;

    public function create(User $user, array $data, UploadedFile $file): Document;

    public function update(Document $document, array $data, ?UploadedFile $file): Document;

    public function delete(Document $document): void;
}
