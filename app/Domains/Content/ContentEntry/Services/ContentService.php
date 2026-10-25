<?php

namespace App\Domains\Content\ContentEntry\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Content\ContentEntry\DTOs\ContentEntryDTO;
use App\Domains\Content\ContentEntry\Enums\ContentType;
use App\Domains\Content\ContentEntry\Repositories\ContentEntryRepositoryInterface;
use App\Domains\Media\Media\Services\MediaService;
use App\Domains\Settings\Setting\Repositories\SettingRepositoryInterface;
use App\Domains\Settings\Setting\Services\SettingsService;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Content\ContentEntry;
use App\Models\Settings\Setting;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** The public website's editable content: page copy (one settings key) plus content entries. */
class ContentService
{
    private const SITE_KEY = 'website';

    /** A photo of the venue shipped with the website (FE public/images/site, 480/960/1600 wide). */
    public static function sitePhoto(string $name): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/images/site/{$name}.webp";
    }

    /** Page copy. Unset keys fall back to these, so the site never renders blank. */
    public static function siteDefaults(): array
    {
        return [
            'location_label' => 'Mubende, Uganda',
            'hero_eyebrow' => 'Gardens · Events · Stays',
            'hero_title' => 'Where Uganda gathers to celebrate',
            'hero_subtitle' => 'Green lawns, shady trees and a sparkling pool, ready for your wedding, Kwanjula, graduation party or a quiet weekend away.',
            'hero_image_url' => self::sitePhoto('cottages-blue-sky-mubende'),
            'intro_title' => 'A garden made for gatherings',
            'intro_body' => "Alcawaka Gardens is a green escape built around people coming together. Our lawns hold anything from an intimate family lunch to a 600-guest wedding, and our team takes care of the details so you can enjoy the day.\n\nStay the night in a garden-view room, swim in the pool and wake up to birdsong.",
            'intro_image_url' => self::sitePhoto('garden-lawn-outdoor-seating'),
            'stats' => [
                ['value' => '600+', 'label' => 'guests on the main lawn'],
                ['value' => '5', 'label' => 'event spaces'],
                ['value' => '7 days', 'label' => 'open every week'],
            ],
            'events_title' => 'Events we host',
            'events_intro' => 'Every celebration is different. Tell us what you are planning and we will shape the garden around it: tents, décor, catering, sound and parking.',
            'stay_title' => 'Stay in the garden',
            'stay_intro' => 'Quiet rooms a short walk from the lawns and the pool. Perfect for wedding parties, out-of-town guests and weekend breaks.',
            'about_title' => 'Our story',
            'about_body' => "Alcawaka Gardens began as a family garden where relatives gathered for Sunday lunch, introductions and Christmas. Friends started asking to hold their own celebrations here, and the garden grew into a venue.\n\nToday we host weddings, Kwanjula and Kuhingira ceremonies, corporate retreats and birthday parties, and we have rooms for guests who want to stay. We still run it like a family home: warm, unhurried and proudly Ugandan.",
            'about_image_url' => self::sitePhoto('garden-cottages-palms'),
            'cta_title' => 'Planning something special?',
            'cta_body' => 'Book a free site visit. Walk the gardens with our events team and get a quote the same week.',
            'cta_image_url' => self::sitePhoto('veranda-garden-view'),
            'opening_hours' => 'Open daily, 7:00 am – 11:00 pm',
            'whatsapp' => null,
            'instagram_url' => null,
            'facebook_url' => null,
            'tiktok_url' => null,
            'map_query' => 'Bakijjulula, Mubende, Uganda',
        ];
    }

    public function __construct(
        private ContentEntryRepositoryInterface $repository,
        private SettingRepositoryInterface $settings,
        private SettingsService $hotel,
        private AuditLogger $audit,
        private MediaService $media,
    ) {}

    /** @return array<string, mixed> */
    public function site(): array
    {
        $saved = $this->settings->all()[self::SITE_KEY] ?? [];

        return array_merge(self::siteDefaults(), array_intersect_key(is_array($saved) ? $saved : [], self::siteDefaults()));
    }

    /** @param array<string, mixed> $values */
    public function updateSite(array $values): array
    {
        $before = $this->site();
        $after = array_merge($before, array_intersect_key($values, self::siteDefaults()));
        DB::transaction(function () use ($before, $after) {
            $this->settings->putMany([self::SITE_KEY => $after]);
            $this->audit->record('website.updated', new Setting(['key' => self::SITE_KEY]), AuditLogger::diff($before, $after));
        });
        foreach ($before as $key => $url) {
            if (str_ends_with($key, '_image_url') && $url !== $after[$key]) {
                $this->releaseImage($url);
            }
        }

        return $after;
    }

    /**
     * Everything the public website needs in one request.
     *
     * @return array{hotel: array<string, mixed>, site: array<string, mixed>, entries: array<string, list<ContentEntry>>}
     */
    public function publicSite(): array
    {
        $entries = array_fill_keys(array_column(ContentType::cases(), 'value'), []);
        foreach ($this->repository->published() as $entry) {
            $entries[$entry->type->value][] = $entry;
        }

        // What's on: upcoming only, soonest first.
        $today = Carbon::now('Africa/Kampala')->startOfDay()->format('Y-m-d H:i');
        $entries['event'] = collect($entries['event'])
            ->filter(fn (ContentEntry $e) => ($e->data['starts_at'] ?? '') >= $today)
            ->sortBy(fn (ContentEntry $e) => $e->data['starts_at'])
            ->values()->all();

        $hotel = array_intersect_key($this->hotel->all(), array_flip(['hotel_name', 'phone', 'email', 'address', 'check_in_time', 'check_out_time', 'cancellation_policy']));

        return ['hotel' => $hotel, 'site' => $this->site(), 'entries' => $entries];
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    public function find(string $id): ContentEntry
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('Content', $id);
    }

    public function create(ContentEntryDTO $dto): ContentEntry
    {
        $data = $dto->attributes;
        $type = ContentType::from($data['type']);
        $this->assertImage($type, $data['image_url'] ?? null);
        $data['data'] = $this->validData($type, $data['data'] ?? []);
        $data['slug'] = $this->uniqueSlug($type, $data['title']);
        $data['sort_order'] ??= $this->repository->nextSortOrder($type->value);

        return DB::transaction(function () use ($data) {
            $entry = $this->repository->create($data);
            $this->audit->record('content.created', $entry);

            return $entry;
        });
    }

    public function update(string $id, ContentEntryDTO $dto): ContentEntry
    {
        $entry = $this->find($id);
        $data = $dto->attributes;
        unset($data['type']); // an entry never changes type: its data fields would no longer fit
        $this->assertImage($entry->type, $dto->has('image_url') ? $data['image_url'] : $entry->image_url);
        if ($dto->has('data')) {
            $data['data'] = $this->validData($entry->type, $data['data'] ?? []);
        }
        if ($dto->has('title') && $data['title'] !== $entry->title) {
            $data['slug'] = $this->uniqueSlug($entry->type, $data['title'], $entry->id);
        }
        $before = $entry->attributesToArray();

        $updated = DB::transaction(function () use ($entry, $data, $before) {
            $updated = $this->repository->update($entry, $data);
            $this->audit->record('content.updated', $updated, AuditLogger::diff($before, $data));

            return $updated;
        });
        if ($before['image_url'] !== $updated->image_url) {
            $this->releaseImage($before['image_url']);
        }

        return $updated;
    }

    public function delete(string $id): void
    {
        $entry = $this->find($id);
        DB::transaction(function () use ($entry) {
            $this->repository->delete($entry);
            $this->audit->record('content.deleted', $entry, ['title' => ['from' => $entry->title, 'to' => null]]);
        });
        $this->releaseImage($entry->image_url);
    }

    /** Slugs are page addresses (/events/weddings). A clashing title gets a short suffix instead of failing. */
    private function uniqueSlug(ContentType $type, string $title, ?string $exceptId = null): string
    {
        $base = Str::slug($title) ?: 'item';
        $slug = $base;
        while ($this->repository->existsBySlug($type->value, $slug, $exceptId)) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    /**
     * The extra fields depend on the type, so they are checked here rather than in the FormRequest.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validData(ContentType $type, array $data): array
    {
        $rules = [];
        foreach ($type->dataRules() as $key => $keyRules) {
            $rules["data.{$key}"] = $keyRules;
        }

        return Validator::make(['data' => $data], $rules)->validate()['data'] ?? [];
    }

    /** A photo nothing on the website uses any more: delete the uploaded file (after the save, so a failed save keeps it). */
    private function releaseImage(?string $url): void
    {
        if ($url && ! $this->repository->imageInUse($url) && ! in_array($url, $this->site(), true)) {
            $this->media->deleteByUrl('website_images', $url);
        }
    }

    private function assertImage(ContentType $type, ?string $imageUrl): void
    {
        if ($type->requiresImage() && ! $imageUrl) {
            throw new EntityValidationException('Add a photo.', ['image_url' => ['Add a photo.']]);
        }
    }
}
