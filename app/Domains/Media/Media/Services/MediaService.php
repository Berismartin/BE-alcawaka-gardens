<?php

namespace App\Domains\Media\Media\Services;

use App\Domains\Media\Media\DTOs\UploadMediaDTO;
use App\Domains\Media\Media\Repositories\MediaRepositoryInterface;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Media\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MediaService
{
    public function __construct(private MediaRepositoryInterface $repository) {}

    /** @return array<string, mixed> */
    public static function collection(string $name): array
    {
        return config("media.collections.{$name}") ?? throw new EntityValidationException("Unknown upload collection: {$name}");
    }

    public function find(string $id): Media
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('File', $id);
    }

    public function upload(UploadMediaDTO $dto): Media
    {
        $config = self::collection($dto->collection);

        if ($dto->mediableType !== null) {
            $class = Relation::getMorphedModel($dto->mediableType);
            if (! $class || ! $class::query()->whereKey($dto->mediableId)->exists()) {
                throw new EntityValidationException('The record this file belongs to was not found.', [
                    'mediable_id' => ['The record this file belongs to was not found.'],
                ]);
            }
        }

        $disk = config("media.disks.{$config['visibility']}");
        $extension = $dto->file->guessExtension() ?: $dto->file->extension(); // from content, not the client's name
        $path = $dto->file->storeAs($dto->collection.'/'.now()->format('Y/m'), Str::uuid().'.'.$extension, $disk);
        if ($path === false) {
            throw new EntityValidationException("Couldn't save the file. Please try again.");
        }

        [$width, $height] = str_starts_with((string) $dto->file->getMimeType(), 'image/')
            ? (@getimagesize($dto->file->getRealPath()) ?: [null, null])
            : [null, null];

        try {
            return $this->repository->create([
                'collection' => $dto->collection,
                'disk' => $disk,
                'path' => $path,
                'visibility' => $config['visibility'],
                'mime' => $dto->file->getMimeType(),
                'size' => $dto->file->getSize(),
                'original_name' => Str::limit($dto->file->getClientOriginalName(), 250, ''),
                'width' => $width,
                'height' => $height,
                'mediable_type' => $dto->mediableType,
                'mediable_id' => $dto->mediableId,
                'sort_order' => $this->repository->nextSortOrder($dto->collection, $dto->mediableType, $dto->mediableId),
                'uploaded_by' => $dto->uploadedBy,
            ]);
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path); // don't leave an orphan file behind
            throw $e;
        }
    }

    /**
     * Gallery order: ids in the order they should appear. All must share one owner and collection.
     *
     * @param  list<string>  $ids
     */
    public function reorder(array $ids): void
    {
        $items = $this->repository->findMany($ids)->keyBy('id');
        $owners = $items->map(fn (Media $m) => $m->collection.'|'.$m->mediable_type.'|'.$m->mediable_id)->unique();
        if ($items->count() !== count($ids) || $owners->count() !== 1) {
            throw new EntityValidationException('These files cannot be reordered together.', ['ids' => ['These files cannot be reordered together.']]);
        }

        foreach (array_values($ids) as $i => $id) {
            $this->repository->setSortOrder($items[$id], $i + 1);
        }
    }

    public function delete(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $this->repository->delete($media);
    }

    public function deleteAllFor(Model $owner): void
    {
        $this->repository->forOwner($owner)->each(fn (Media $m) => $this->delete($m));
    }
}
