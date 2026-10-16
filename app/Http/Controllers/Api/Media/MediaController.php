<?php

namespace App\Http\Controllers\Api\Media;

use App\Actions\Media\ManageMediaAction;
use App\Actions\Media\UploadMediaAction;
use App\Domains\Media\Media\Services\MediaService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\ReorderMediaRequest;
use App\Http\Requests\Media\UploadMediaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function store(UploadMediaRequest $request, UploadMediaAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated(), $request->user()->id), 'File uploaded.', Response::HTTP_CREATED);
    }

    public function reorder(ReorderMediaRequest $request, ManageMediaAction $action): JsonResponse
    {
        $action->reorder($request->user(), $request->validated('ids'));

        return $this->respond(null, 'Order saved.');
    }

    public function destroy(Request $request, string $id, ManageMediaAction $action): JsonResponse
    {
        $action->delete($request->user(), $id);

        return $this->respond(null, 'File deleted.');
    }

    /** Private file download. Reached only through the signed link in MediaResource. */
    public function file(string $id, MediaService $service): StreamedResponse
    {
        $media = $service->find($id);

        return Storage::disk($media->disk)->response($media->path, $media->original_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
