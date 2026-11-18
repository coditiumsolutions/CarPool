<?php

namespace Tests\Feature\Api;

use App\Models\UserDocument;
use App\Models\VehicleDocument;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesData;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use MakesData;

    private function upload($user, string $url, array $fields)
    {
        return $this->as($user)->post($url, $fields, ['Accept' => 'application/json']);
    }

    public function test_user_uploads_and_lists_documents(): void
    {
        Storage::fake('local');
        $user = $this->user('driver');

        $this->upload($user, '/api/v1/documents', ['type' => 'cnic', 'file' => $this->pngFile('cnic.png')])
            ->assertCreated()
            ->assertJsonPath('data.type', 'cnic')->assertJsonPath('data.status', 'pending')->assertJsonPath('data.rejection_reason', null);

        $row = $this->as($user)->getJson('/api/v1/documents')->assertOk()->assertJsonCount(1, 'data')->json('data.0');
        $this->assertStringContainsString("/documents/{$row['id']}/file", $row['file_url']);
        $this->assertArrayNotHasKey('document_image', $row, 'storage path must not leak');
        Storage::disk('local')->assertExists(UserDocument::first()->document_image);
    }

    public function test_status_and_rejection_reason_are_returned(): void
    {
        $user = $this->user('driver');
        UserDocument::create(['user_id' => $user->id, 'document_type' => 'driving_license', 'verification_status' => 'rejected', 'rejection_reason' => 'Photo is blurry']);
        UserDocument::create(['user_id' => $user->id, 'document_type' => 'cnic', 'verification_status' => 'approved']);

        $rows = collect($this->as($user)->getJson('/api/v1/documents')->json('data'))->keyBy('type');
        $this->assertSame('rejected', $rows['driving_license']['status']);
        $this->assertSame('Photo is blurry', $rows['driving_license']['rejection_reason']);
        $this->assertSame('approved', $rows['cnic']['status']);
    }

    public function test_reupload_replaces_unapproved_but_keeps_approved(): void
    {
        Storage::fake('local');
        $user = $this->user('driver');

        $this->upload($user, '/api/v1/documents', ['type' => 'cnic', 'file' => $this->pngFile('a.png')])->assertCreated();
        $first = UserDocument::first();
        $this->upload($user, '/api/v1/documents', ['type' => 'cnic', 'file' => $this->pngFile('b.png')])->assertCreated();
        $this->assertSame(1, UserDocument::count());
        Storage::disk('local')->assertMissing($first->document_image);

        UserDocument::query()->update(['verification_status' => 'approved']);
        $this->upload($user, '/api/v1/documents', ['type' => 'cnic', 'file' => $this->pngFile('c.png')])->assertCreated();
        $this->assertSame(2, UserDocument::count());
    }

    public function test_upload_validation(): void
    {
        Storage::fake('local');
        $user = $this->user();

        $this->upload($user, '/api/v1/documents', ['type' => 'cnic'])->assertStatus(422);
        $this->upload($user, '/api/v1/documents', ['file' => $this->pngFile()])->assertStatus(422);
        $this->upload($user, '/api/v1/documents', ['type' => 'cnic', 'file' => \Illuminate\Http\UploadedFile::fake()->create('x.exe', 10)])->assertStatus(422);
    }

    public function test_only_owner_or_admin_can_download_a_file(): void
    {
        Storage::fake('local');
        $owner = $this->user('driver');
        $this->upload($owner, '/api/v1/documents', ['type' => 'cnic', 'file' => $this->pngFile()])->assertCreated();
        $id = UserDocument::first()->id;

        $this->as($owner)->get("/api/v1/documents/$id/file")->assertOk();
        $this->as($this->user('admin'))->get("/api/v1/documents/$id/file")->assertOk();
        $this->as($this->user())->getJson("/api/v1/documents/$id/file")->assertStatus(403);
    }

    public function test_vehicle_documents_and_verification_status(): void
    {
        Storage::fake('local');
        $driver = $this->user('driver');
        $vehicle = $this->vehicle($driver, ['is_verified' => false]);

        $this->as($driver)->getJson("/api/v1/vehicles/{$vehicle->id}")->assertJsonPath('data.verification_status', 'pending');

        $this->upload($driver, "/api/v1/vehicles/{$vehicle->id}/documents", ['type' => 'registration', 'file' => $this->pngFile()])
            ->assertCreated()->assertJsonPath('data.type', 'registration')->assertJsonPath('data.status', 'pending');
        $this->upload($driver, "/api/v1/vehicles/{$vehicle->id}/documents", ['type' => 'insurance', 'file' => $this->pngFile()])->assertCreated();
        $this->as($driver)->getJson("/api/v1/vehicles/{$vehicle->id}/documents")->assertOk()->assertJsonCount(2, 'data');

        VehicleDocument::where('document_type', 'insurance')->update(['verification_status' => 'rejected', 'rejection_reason' => 'Expired']);
        $this->as($driver)->getJson('/api/v1/vehicles')->assertJsonPath('data.0.verification_status', 'rejected');

        $vehicle->update(['is_verified' => true]);
        $this->as($driver)->getJson("/api/v1/vehicles/{$vehicle->id}")->assertJsonPath('data.verification_status', 'approved');
    }

    public function test_vehicle_documents_belong_to_the_owner_only(): void
    {
        Storage::fake('local');
        $vehicle = $this->vehicle($this->user('driver'));
        $stranger = $this->user('driver');

        $this->upload($stranger, "/api/v1/vehicles/{$vehicle->id}/documents", ['type' => 'registration', 'file' => $this->pngFile()])->assertStatus(403);
        $this->as($stranger)->getJson("/api/v1/vehicles/{$vehicle->id}/documents")->assertStatus(403);
        $this->upload($vehicle->user, "/api/v1/vehicles/{$vehicle->id}/documents", ['type' => 'passport', 'file' => $this->pngFile()])->assertStatus(422);
    }
}
