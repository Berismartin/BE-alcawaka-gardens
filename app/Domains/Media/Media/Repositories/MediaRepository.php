<?php

namespace App\Domains\Media\Media\Repositories;

use App\Models\Media\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MediaRepository implements MediaRepositoryInterface
{
    public function findById(string $id): ?Media
    {
        return Media::find($id);
    }

    public function create(array $data): Media
    {
        return Media::create($data);
    }

    public function nextSortOrder(string $collection, ?string $mediableType, ?string $mediableId): int
    {
        return (int) Media::where('collection', $collection)
            ->where('mediable_type', $mediableType)
            ->where('mediable_id', $mediableId)
            ->max('sort_order') + 1;
    }

    public function forOwner(Model $owner): Collection
    {
        return Media::where('mediable_type', $owner->getMorphClass())->where('mediable_id', $owner->getKey())->get();
    }

    public function findMany(array $ids): Collection
    {
        return Media::whereKey($ids)->get();
    }

    public function setSortOrder(Media $media, int $order): void
    {
        $media->forceFill(['sort_order' => $order])->save();
    }

    public function delete(Media $media): void
    {
        $media->delete();
    }
}
