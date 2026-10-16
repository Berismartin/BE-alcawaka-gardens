<?php

namespace App\Http\Controllers\Api\Content;

use App\Actions\Content\ContentEntry\CreateContentEntryAction;
use App\Actions\Content\ContentEntry\DeleteContentEntryAction;
use App\Actions\Content\ContentEntry\PaginateContentEntriesAction;
use App\Actions\Content\ContentEntry\PublicSiteAction;
use App\Actions\Content\ContentEntry\UpdateContentEntryAction;
use App\Actions\Content\ContentEntry\UpdateSiteContentAction;
use App\Domains\Content\ContentEntry\Services\ContentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ContentEntry\ListContentEntriesRequest;
use App\Http\Requests\Content\ContentEntry\StoreContentEntryRequest;
use App\Http\Requests\Content\ContentEntry\UpdateContentEntryRequest;
use App\Http\Requests\Content\UpdateSiteContentRequest;
use App\Http\Resources\Content\ContentEntryResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/** Website CMS. */
class ContentController extends Controller
{
    /** Public website: page copy, hotel contacts and every published entry, in one request. */
    public function site(PublicSiteAction $action): JsonResponse
    {
        return $this->respond($action->execute());
    }

    public function siteContent(ContentService $service): JsonResponse
    {
        return $this->respond($service->site());
    }

    public function updateSiteContent(UpdateSiteContentRequest $request, UpdateSiteContentAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Website text saved.');
    }

    public function index(ListContentEntriesRequest $request, PaginateContentEntriesAction $action): JsonResponse
    {
        return $this->respondPage($action->execute($request->toListQuery()), ContentEntryResource::class);
    }

    public function store(StoreContentEntryRequest $request, CreateContentEntryAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Saved.', Response::HTTP_CREATED);
    }

    public function update(UpdateContentEntryRequest $request, string $id, UpdateContentEntryAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Saved.');
    }

    public function destroy(string $id, DeleteContentEntryAction $action): JsonResponse
    {
        $action->execute($id);

        return $this->respond(null, 'Deleted.');
    }
}
