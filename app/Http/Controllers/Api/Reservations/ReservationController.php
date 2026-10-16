<?php

namespace App\Http\Controllers\Api\Reservations;

use App\Actions\Reservations\Reservation\CalendarAction;
use App\Actions\Reservations\Reservation\CancelReservationAction;
use App\Actions\Reservations\Reservation\CheckInAction;
use App\Actions\Reservations\Reservation\CheckOutAction;
use App\Actions\Reservations\Reservation\CreateReservationAction;
use App\Actions\Reservations\Reservation\MarkNoShowAction;
use App\Actions\Reservations\Reservation\PaginateReservationsAction;
use App\Actions\Reservations\Reservation\SearchAvailabilityAction;
use App\Actions\Reservations\Reservation\ShowReservationAction;
use App\Actions\Reservations\Reservation\UpdateReservationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\AvailabilityRequest;
use App\Http\Requests\Reservations\CalendarRequest;
use App\Http\Requests\Reservations\CancelReservationRequest;
use App\Http\Requests\Reservations\ListReservationsRequest;
use App\Http\Requests\Reservations\StoreReservationRequest;
use App\Http\Requests\Reservations\UpdateReservationRequest;
use App\Http\Resources\Reservations\ReservationResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReservationController extends Controller
{
    public function index(ListReservationsRequest $request, PaginateReservationsAction $action): JsonResponse
    {
        return $this->respondPage($action->execute($request->toListQuery()), ReservationResource::class);
    }

    public function show(string $id, ShowReservationAction $action): JsonResponse
    {
        return $this->respond($action->execute($id));
    }

    public function availability(AvailabilityRequest $request, SearchAvailabilityAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()));
    }

    public function calendar(CalendarRequest $request, CalendarAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated('from'), (int) $request->validated('days')));
    }

    public function store(StoreReservationRequest $request, CreateReservationAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Booking created.', Response::HTTP_CREATED);
    }

    public function update(UpdateReservationRequest $request, string $id, UpdateReservationAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Booking updated.');
    }

    public function checkIn(string $id, CheckInAction $action): JsonResponse
    {
        return $this->respond($action->execute($id), 'Guest checked in.');
    }

    public function checkOut(string $id, CheckOutAction $action): JsonResponse
    {
        return $this->respond($action->execute($id), 'Guest checked out. The room is marked for cleaning.');
    }

    public function cancel(CancelReservationRequest $request, string $id, CancelReservationAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated('reason')), 'Booking cancelled.');
    }

    public function noShow(string $id, MarkNoShowAction $action): JsonResponse
    {
        return $this->respond($action->execute($id), 'Marked as no-show.');
    }
}
