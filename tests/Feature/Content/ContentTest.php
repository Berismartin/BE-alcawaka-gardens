<?php

namespace Tests\Feature\Content;

use App\Domains\Users\User\Enums\UserRole;
use App\Mail\EnquiryReceivedMail;
use App\Models\Settings\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_edits_the_website_and_the_public_site_shows_it(): void
    {
        $this->actingAsRole(UserRole::Manager);

        $this->postJson('/api/portal/content', ['type' => 'event_type', 'title' => 'Weddings', 'data' => ['capacity' => 'lots']])
            ->assertUnprocessable()->assertJsonValidationErrors('data.capacity');
        $this->postJson('/api/portal/content', ['type' => 'event_type', 'title' => 'Weddings', 'data' => ['capacity' => 600, 'features' => ['Tents'], 'secret' => 'x']])
            ->assertCreated()->assertJsonPath('data.slug', 'weddings')->assertJsonPath('data.data.capacity', 600)->assertJsonMissingPath('data.data.secret');
        // Same title: a different address, not an error.
        $this->assertNotSame('weddings', $this->postJson('/api/portal/content', ['type' => 'event_type', 'title' => 'Weddings'])->json('data.slug'));

        $this->postJson('/api/portal/content', ['type' => 'gallery', 'title' => 'Lawn'])->assertUnprocessable()->assertJsonValidationErrors('image_url');
        $this->postJson('/api/portal/content', ['type' => 'event', 'title' => 'Garden night'])->assertUnprocessable()->assertJsonValidationErrors('data.starts_at');
        $this->postJson('/api/portal/content', ['type' => 'event', 'title' => 'Last year', 'data' => ['starts_at' => now()->subYear()->format('Y-m-d H:i')]])->assertCreated();
        $soon = $this->postJson('/api/portal/content', ['type' => 'event', 'title' => 'Garden night', 'data' => ['starts_at' => now()->addWeek()->format('Y-m-d H:i')]])->json('data.id');
        $hidden = $this->postJson('/api/portal/content', ['type' => 'faq', 'title' => 'Draft?', 'is_published' => false])->json('data.id');

        $this->putJson('/api/portal/content/site', ['hero_title' => 'Celebrate in the garden', 'stats' => [['value' => '600', 'label' => 'guests']], 'unknown' => 'x'])
            ->assertOk()->assertJsonPath('data.hero_title', 'Celebrate in the garden')->assertJsonMissingPath('data.unknown');
        $this->putJson('/api/portal/content/site', ['hero_image_url' => 'not a url'])->assertUnprocessable();

        $this->getJson('/api/portal/content?filter[type]=event_type')->assertOk()->assertJsonPath('meta.total', 2);

        // Signed out: the public site.
        $this->app['auth']->forgetGuards();
        $site = $this->getJson('/api/site')->assertOk()
            ->assertJsonPath('data.site.hero_title', 'Celebrate in the garden')
            ->assertJsonPath('data.hotel.hotel_name', 'Alcawaka Gardens')
            ->assertJsonCount(2, 'data.entries.event_type')
            ->assertJsonCount(1, 'data.entries.event') // past events drop off
            ->assertJsonPath('data.entries.event.0.id', $soon)
            ->json('data.entries');
        $this->assertNotContains($hidden, array_column($site['faq'], 'id'));
        $this->getJson('/api/portal/content')->assertUnauthorized();
    }

    public function test_front_desk_cannot_edit_the_website(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);
        $this->postJson('/api/portal/content', ['type' => 'faq', 'title' => 'Q'])->assertForbidden();
        $this->putJson('/api/portal/content/site', ['hero_title' => 'X'])->assertForbidden();
    }

    public function test_website_enquiry_reaches_the_front_desk(): void
    {
        $this->postJson('/api/enquiries', ['name' => 'Aisha Nansubuga', 'phone' => '0772 123 456', 'message' => 'Hi', 'website' => 'http://spam'])
            ->assertUnprocessable()->assertJsonValidationErrors('website');
        $this->postJson('/api/enquiries', ['name' => 'Aisha Nansubuga', 'phone' => 'call me', 'message' => 'Hi'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/api/enquiries', [
            'name' => 'Aisha Nansubuga', 'phone' => '0772 123 456', 'event_type' => 'Weddings',
            'event_date' => now()->addMonths(3)->toDateString(), 'guests' => 300, 'message' => 'Can we visit on Saturday?',
        ])->assertCreated()->assertJsonPath('data', null);

        $desk = $this->actingAsRole(UserRole::FrontDesk);
        $id = $this->getJson('/api/portal/enquiries?filter[status]=new')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.guests', 300)->json('data.0.id');
        $this->patchJson("/api/portal/enquiries/{$id}", ['status' => 'contacted', 'notes' => 'Visit booked Sat 10am'])
            ->assertOk()->assertJsonPath('data.status', 'contacted');
        $this->assertSame(0, $desk->notifications()->count()); // the alert went to staff who existed when it arrived

        $this->actingAsRole(UserRole::Housekeeping);
        $this->getJson('/api/portal/enquiries')->assertForbidden();
    }

    public function test_new_enquiry_emails_the_hotel_and_staff_mark_it_attended(): void
    {
        Mail::fake();
        $enquiry = ['name' => 'Aisha Nansubuga', 'phone' => '0772 123 456', 'email' => 'aisha@example.com', 'event_type' => 'Weddings', 'message' => 'Can we visit?'];

        $this->postJson('/api/enquiries', $enquiry)->assertCreated(); // no hotel email in Settings yet: nothing sent, still saved
        Mail::assertNothingSent();

        Setting::create(['key' => 'email', 'value' => 'alcawakagardens@gmail.com']);
        $this->postJson('/api/enquiries', $enquiry)->assertCreated();
        Mail::assertSent(EnquiryReceivedMail::class, fn (EnquiryReceivedMail $m) => $m->hasTo('alcawakagardens@gmail.com')
            && $m->hasReplyTo('aisha@example.com') && $m->hasSubject('Weddings enquiry from Aisha Nansubuga')
            && str_ends_with($m->portalUrl, "/portal/enquiries/{$m->enquiry->id}"));

        $desk = $this->actingAsRole(UserRole::FrontDesk);
        $id = $this->getJson('/api/portal/enquiries')->json('data.0.id');
        $this->getJson("/api/portal/enquiries/{$id}")->assertOk()->assertJsonPath('data.email', 'aisha@example.com')->assertJsonPath('data.attended', null);

        $this->patchJson("/api/portal/enquiries/{$id}", ['status' => 'contacted'])->assertOk()
            ->assertJsonPath('data.attended.by', $desk->name);
        $first = $this->getJson("/api/portal/enquiries/{$id}")->json('data.attended.at');
        $this->travel(1)->hours();
        $this->patchJson("/api/portal/enquiries/{$id}", ['status' => 'booked'])->assertJsonPath('data.attended.at', $first); // first attended time is kept
        $this->patchJson("/api/portal/enquiries/{$id}", ['status' => 'new'])->assertJsonPath('data.attended', null); // back to "not attended"

        $this->getJson('/api/portal/enquiries/'.str_repeat('0', 8).'-0000-0000-0000-000000000000')->assertNotFound();
        $this->actingAsRole(UserRole::Housekeeping);
        $this->getJson("/api/portal/enquiries/{$id}")->assertForbidden();
    }

    public function test_replaced_and_deleted_photos_are_removed_from_storage(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']); // absolute, like the real public disk
        $this->actingAsRole(UserRole::Manager);
        $upload = fn () => $this->post('/api/portal/media', ['collection' => 'website_images', 'file' => UploadedFile::fake()->image('lawn.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertCreated()->json('data');

        $a = $upload();
        $b = $upload();
        $files = fn () => Storage::disk('public')->allFiles();
        $this->assertCount(2, $files());

        // Two entries share photo A: replacing it on one keeps the file.
        $one = $this->postJson('/api/portal/content', ['type' => 'gallery', 'title' => 'One', 'image_url' => $a['url']])->json('data.id');
        $two = $this->postJson('/api/portal/content', ['type' => 'gallery', 'title' => 'Two', 'image_url' => $a['url']])->json('data.id');
        $this->patchJson("/api/portal/content/{$one}", ['image_url' => $b['url']])->assertOk();
        $this->assertCount(2, $files());

        // The last user of A goes: A's file and record go with it.
        $this->deleteJson("/api/portal/content/{$two}")->assertOk();
        $this->assertCount(1, $files());
        $this->assertDatabaseMissing('media', ['id' => $a['id']]);

        // Page photos too, while stock photos (other sites) are never touched.
        $this->putJson('/api/portal/content/site', ['hero_image_url' => $b['url']])->assertOk();
        $this->deleteJson("/api/portal/content/{$one}")->assertOk();
        $this->assertCount(1, $files()); // still the hero
        $this->putJson('/api/portal/content/site', ['hero_image_url' => 'https://images.unsplash.com/photo-1?w=10'])->assertOk();
        $this->assertCount(0, $files());
    }
}
