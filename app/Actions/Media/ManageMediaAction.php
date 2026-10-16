<?php

namespace App\Actions\Media;

use App\Domains\Media\Media\Services\MediaService;
use App\Models\Media\Media;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/** Delete and reorder: the caller needs the permission of the file's collection. */
class ManageMediaAction
{
    public function __construct(private MediaService $service) {}

    public function delete(User $user, string $id): void
    {
        $media = $this->service->find($id);
        $this->authorize($user, $media);
        $this->service->delete($media);
    }

    /** @param list<string> $ids */
    public function reorder(User $user, array $ids): void
    {
        $this->authorize($user, $this->service->find($ids[0]));
        $this->service->reorder($ids);
    }

    private function authorize(User $user, Media $media): void
    {
        if (! $user->role->can(MediaService::collection($media->collection)['permission'])) {
            throw new AuthorizationException;
        }
    }
}
