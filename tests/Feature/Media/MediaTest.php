<?php

namespace Tests\Feature\Media;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\Facilities\Facility;
use App\Models\Media\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function as(UserRole $role): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        Sanctum::actingAs($user);
    }

    private function upload(Facility $facility, UploadedFile $file)
    {
        return $this->post('/api/portal/media', [
            'collection' => 'facility_photos',
            'mediable_type' => 'facility',
            'mediable_id' => $facility->id,
            'file' => $file,
        ], ['Accept' => 'application/json']);
    }

    public function test_manager_uploads_a_facility_photo_to_the_public_disk(): void
    {
        $this->as(UserRole::Manager);
        $facility = Facility::factory()->create();

        $res = $this->upload($facility, UploadedFile::fake()->image('pool.jpg', 1200, 800))
            ->assertCreated()
            ->assertJsonPath('data.width', 1200)
            ->assertJsonPath('data.sort_order', 1);

        Storage::disk('public')->assertExists(Media::first()->path);
        $this->assertStringContainsString('/storage/facility_photos/', $res->json('data.url'));

        $this->upload($facility, UploadedFile::fake()->image('garden.png'))->assertJsonPath('data.sort_order', 2);
        $this->getJson('/api/facilities')->assertJsonCount(2, 'data.0.photos');
    }

    public function test_wrong_type_size_or_owner_is_rejected_with_a_clear_message(): void
    {
        $this->as(UserRole::Manager);
        $facility = Facility::factory()->create();

        $this->upload($facility, UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Files must be JPG, PNG, WEBP under 5 MB.');
        $this->upload($facility, UploadedFile::fake()->image('huge.jpg')->size(6000))->assertUnprocessable();

        $this->post('/api/portal/media', [
            'collection' => 'facility_photos', 'mediable_type' => 'facility',
            'mediable_id' => '00000000-0000-0000-0000-000000000000', 'file' => UploadedFile::fake()->image('x.jpg'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('mediable_id');
    }

    public function test_upload_and_delete_need_the_collection_permission(): void
    {
        $facility = Facility::factory()->create();
        $this->as(UserRole::FrontDesk); // can view facilities, not manage them
        $this->upload($facility, UploadedFile::fake()->image('pool.jpg'))->assertForbidden()->assertJsonPath('success', false);

        $media = Media::create([
            'collection' => 'facility_photos', 'disk' => 'public', 'path' => 'x.jpg', 'visibility' => 'public',
            'mime' => 'image/jpeg', 'size' => 1, 'original_name' => 'x.jpg',
        ]);
        $this->deleteJson("/api/portal/media/{$media->id}")->assertForbidden();
    }

    public function test_private_files_are_only_served_through_a_signed_link(): void
    {
        $this->as(UserRole::Manager);
        config(['media.collections.guest_id.mediable' => ['facility']]); // guest model arrives in phase 2

        $url = $this->post('/api/portal/media', [
            'collection' => 'guest_id', 'mediable_type' => 'facility',
            'mediable_id' => Facility::factory()->create()->id,
            'file' => UploadedFile::fake()->create('passport.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.url');

        Storage::disk('local')->assertExists(Media::first()->path);
        $this->get($url)->assertOk();
        $this->getJson('/api/media/'.Media::first()->id.'/file')->assertForbidden();
    }

    public function test_reorder_and_owner_deletion_cleans_up_files(): void
    {
        $this->as(UserRole::Manager);
        $facility = Facility::factory()->create();
        $a = $this->upload($facility, UploadedFile::fake()->image('a.jpg'))->json('data.id');
        $b = $this->upload($facility, UploadedFile::fake()->image('b.jpg'))->json('data.id');

        $this->patchJson('/api/portal/media/order', ['ids' => [$b, $a]])->assertOk();
        $this->assertSame([$b, $a], $facility->photos()->pluck('id')->all());

        $paths = Media::pluck('path')->all();
        $this->deleteJson("/api/portal/facilities/{$facility->id}")->assertOk();
        $this->assertSame(0, Media::count());
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }
}
