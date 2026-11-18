<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleDocumentController extends ApiController
{
    public const TYPES = ['registration', 'insurance', 'driving_licence', 'driving_license', 'fitness', 'other'];

    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->owned($request, $vehicle);

        return $this->ok($vehicle->documents()->latest('id')->get()->map(fn ($d) => $this->row($d))->values());
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->owned($request, $vehicle);

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
        ]);

        $vehicle->documents()->where('document_type', $data['type'])->where('verification_status', '!=', 'approved')->get()
            ->each(function (VehicleDocument $old) {
                $old->document_image && Storage::disk('local')->delete($old->document_image);
                $old->delete();
            });

        $document = $vehicle->documents()->create([
            'document_type' => $data['type'],
            'document_image' => $request->file('file')->store("vehicle-documents/{$vehicle->id}", 'local'),
            'expiry_date' => $data['expiry_date'] ?? null,
            'verification_status' => 'pending',
        ]);

        return $this->created($this->row($document), 'Document uploaded. It will be reviewed shortly.');
    }

    public function file(Request $request, Vehicle $vehicle, VehicleDocument $document): StreamedResponse
    {
        ($vehicle->user_id === $request->user()->id || $request->user()->isAdmin()) || $this->deny('Not your vehicle.');
        $document->vehicle_id === $vehicle->id || $this->deny('Document not found.', 404);
        $document->document_image && Storage::disk('local')->exists($document->document_image) || $this->deny('File not found.', 404);

        return Storage::disk('local')->response($document->document_image);
    }

    private function owned(Request $request, Vehicle $vehicle): void
    {
        $vehicle->user_id === $request->user()->id || $this->deny('This is not your vehicle.');
    }

    private function row(VehicleDocument $d): array
    {
        return [
            'id' => $d->id,
            'vehicle_id' => $d->vehicle_id,
            'type' => $d->document_type,
            'status' => $d->verification_status,        // pending | approved | rejected
            'rejection_reason' => $d->rejection_reason,
            'expiry_date' => $d->expiry_date?->format('Y-m-d'),
            'file_url' => $d->document_image ? route('api.v1.vehicles.documents.file', [$d->vehicle_id, $d->id]) : null,
            'created_at' => $d->created_at,
        ];
    }
}
