<?php

namespace App\Http\Controllers\Api\Rates;

use App\Actions\Rates\SeasonalDiscount\CreateSeasonalDiscountAction;
use App\Actions\Rates\SeasonalDiscount\DeleteSeasonalDiscountAction;
use App\Actions\Rates\SeasonalDiscount\ListSeasonalDiscountsAction;
use App\Actions\Rates\SeasonalDiscount\UpdateSeasonalDiscountAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rates\SeasonalDiscountRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SeasonalDiscountController extends Controller
{
    public function index(ListSeasonalDiscountsAction $action): JsonResponse
    {
        return $this->respond($action->execute());
    }

    public function store(SeasonalDiscountRequest $request, CreateSeasonalDiscountAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Discount saved.', Response::HTTP_CREATED);
    }

    public function update(SeasonalDiscountRequest $request, string $id, UpdateSeasonalDiscountAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Discount updated.');
    }

    public function destroy(string $id, DeleteSeasonalDiscountAction $action): JsonResponse
    {
        $action->execute($id);

        return $this->respond(null, 'Discount deleted.');
    }
}
