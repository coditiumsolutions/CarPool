<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\UserDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The user's identity documents (CNIC, driving licence ...). Files are kept on the
 * private disk and only served to the owner or an admin through the file endpoint.
 */
class DocumentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->ok($request->user()->documents()->latest('id')->get()->map(fn ($d) => $this->row($d))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'regex:/^[A-Za-z0-9 _-]{2,50}$/'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
        ]);

        $user = $request->user();

        // A new upload replaces an earlier one of the same type that was never approved.
        $user->documents()->where('document_type', $data['type'])->where('verification_status', '!=', 'approved')->get()
            ->each(function (UserDocument $old) {
                $old->document_image && Storage::disk('local')->delete($old->document_image);
                $old->delete();
            });

        $document = $user->documents()->create([
            'document_type' => $data['type'],
            'document_image' => $request->file('file')->store("documents/{$user->id}", 'local'),
            'expiry_date' => $data['expiry_date'] ?? null,
            'verification_status' => 'pending',
        ]);

        return $this->created($this->row($document), 'Document uploaded. It will be reviewed shortly.');
    }

    public function file(Request $request, UserDocument $document): StreamedResponse
    {
        ($document->user_id === $request->user()->id || $request->user()->isAdmin()) || $this->deny('Not your document.');
        $document->document_image && Storage::disk('local')->exists($document->document_image) || $this->deny('File not found.', 404);

        return Storage::disk('local')->response($document->document_image);
    }

    private function row(UserDocument $d): array
    {
        return [
            'id' => $d->id,
            'type' => $d->document_type,
            'document_type' => $d->document_type,
            'status' => $d->verification_status,        // pending | approved | rejected
            'rejection_reason' => $d->rejection_reason,
            'expiry_date' => $d->expiry_date?->format('Y-m-d'),
            'file_url' => $d->document_image ? route('api.v1.documents.file', $d->id) : null,
            'created_at' => $d->created_at,
        ];
    }
}
