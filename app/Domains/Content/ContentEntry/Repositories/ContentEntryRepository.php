<?php

namespace App\Domains\Content\ContentEntry\Repositories;

use App\Models\Content\ContentEntry;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ContentEntryRepository implements ContentEntryRepositoryInterface
{
    use AppliesListQuery;

    public function published(): Collection
    {
        return ContentEntry::query()->where('is_published', true)->orderBy('sort_order')->orderBy('created_at')->get();
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->paginateList(ContentEntry::query(), $query, ['title', 'summary'], defaultSort: 'sort_order');
    }

    public function findById(string $id): ?ContentEntry
    {
        return ContentEntry::find($id);
    }

    public function existsBySlug(string $type, string $slug, ?string $exceptId = null): bool
    {
        return ContentEntry::where('type', $type)->where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    public function nextSortOrder(string $type): int
    {
        return (int) ContentEntry::where('type', $type)->max('sort_order') + 1;
    }

    public function imageInUse(string $url): bool
    {
        return ContentEntry::where('image_url', $url)->exists();
    }

    public function create(array $data): ContentEntry
    {
        return ContentEntry::create($data);
    }

    public function update(ContentEntry $entry, array $data): ContentEntry
    {
        $entry->update($data);

        return $entry->refresh();
    }

    public function delete(ContentEntry $entry): void
    {
        $entry->delete();
    }
}
