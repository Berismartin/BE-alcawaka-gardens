<?php

namespace App\Domains\Media\Media\Repositories;

use App\Models\Media\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface MediaRepositoryInterface
{
    public function findById(string $id): ?Media;

    public function findByPath(string $collection, string $path): ?Media;

    /** @param array<string, mixed> $data */
    public function create(array $data): Media;

    public function nextSortOrder(string $collection, ?string $mediableType, ?string $mediableId): int;

    /** @return Collection<int, Media> */
    public function forOwner(Model $owner): Collection;

    /**
     * @param  list<string>  $ids
     * @return Collection<int, Media>
     */
    public function findMany(array $ids): Collection;

    public function setSortOrder(Media $media, int $order): void;

    public function delete(Media $media): void;
}
