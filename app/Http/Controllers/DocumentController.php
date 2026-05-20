<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Interfaces\DocumentServiceInterface;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentServiceInterface $documentService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->documentService->getAllForUser($request->user()));
    }

    public function show(Document $document): JsonResponse
    {
        Gate::authorize('view-document', $document);

        return response()->json($document);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        Gate::authorize('create-document');

        $document = $this->documentService->create(
            $request->user(),
            $request->safe()->except('file'),
            $request->file('file')
        );

        return response()->json($document, 201);
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('modify-document', $document);

        $updated = $this->documentService->update(
            $document,
            $request->safe()->except('file'),
            $request->file('file')
        );

        return response()->json($updated);
    }

    public function destroy(Document $document): JsonResponse
    {
        Gate::authorize('modify-document', $document);

        $this->documentService->delete($document);

        return response()->json(null, 204);
    }

    public function download(Document $document): StreamedResponse
    {
        Gate::authorize('view-document', $document);

        return Storage::disk('local')->download($document->file, $document->name);
    }
}
