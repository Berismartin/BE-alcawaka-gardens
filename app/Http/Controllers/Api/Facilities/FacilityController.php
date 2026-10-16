<?php

namespace App\Http\Controllers\Api\Facilities;

use App\Actions\Facilities\Facility\CreateFacilityAction;
use App\Actions\Facilities\Facility\DeleteFacilityAction;
use App\Actions\Facilities\Facility\ListFacilitiesAction;
use App\Actions\Facilities\Facility\PaginateFacilitiesAction;
use App\Actions\Facilities\Facility\ShowFacilityAction;
use App\Actions\Facilities\Facility\UpdateFacilityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Facilities\Facility\ListFacilitiesRequest;
use App\Http\Requests\Facilities\Facility\StoreFacilityRequest;
use App\Http\Requests\Facilities\Facility\UpdateFacilityRequest;
use App\Http\Resources\Facilities\Facility\FacilityResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reference module: copy this shape for every new module.
 * Request -> FormRequest -> Action -> DTO -> Service -> RepositoryInterface -> Model -> Resource.
 */
class FacilityController extends Controller
{
    public function __construct(
        private ListFacilitiesAction $listAction,
        private PaginateFacilitiesAction $paginateAction,
        private ShowFacilityAction $showAction,
        private CreateFacilityAction $createAction,
        private UpdateFacilityAction $updateAction,
        private DeleteFacilityAction $deleteAction,
    ) {}

    /** Public website: active facilities only. */
    public function publicIndex(): JsonResponse
    {
        return $this->respond($this->listAction->execute(activeOnly: true));
    }

    /** Staff portal: paginated, searchable, sortable, filterable. */
    public function index(ListFacilitiesRequest $request): JsonResponse
    {
        return $this->respondPage($this->paginateAction->execute($request->toListQuery()), FacilityResource::class);
    }

    public function show(string $id): JsonResponse
    {
        return $this->respond($this->showAction->execute($id));
    }

    public function store(StoreFacilityRequest $request): JsonResponse
    {
        return $this->respond($this->createAction->execute($request->validated()), 'Facility created.', Response::HTTP_CREATED);
    }

    public function update(UpdateFacilityRequest $request, string $id): JsonResponse
    {
        return $this->respond($this->updateAction->execute($id, $request->validated()), 'Facility updated.');
    }

    public function destroy(string $id): JsonResponse
    {
        $this->deleteAction->execute($id);

        return $this->respond(null, 'Facility deleted.');
    }
}
