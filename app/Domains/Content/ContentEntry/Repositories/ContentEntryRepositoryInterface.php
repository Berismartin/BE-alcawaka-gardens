<?php

namespace App\Domains\Content\ContentEntry\Repositories;

use App\Models\Content\ContentEntry;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ContentEntryRepositoryInterface
{
    /** @return Collection<int, ContentEntry> published entries, in display order */
    public function published(): Collection;

    public function paginate(ListQuery $query): LengthAwarePaginator;

    public function findById(string $id): ?ContentEntry;

    public function existsBySlug(string $type, string $slug, ?string $exceptId = null): bool;

    public function nextSortOrder(string $type): int;

    public function imageInUse(string $url): bool;

    /** @param array<string, mixed> $data */
    public function create(array $data): ContentEntry;

    /** @param array<string, mixed> $data */
    public function update(ContentEntry $entry, array $data): ContentEntry;

    public function delete(ContentEntry $entry): void;
}
