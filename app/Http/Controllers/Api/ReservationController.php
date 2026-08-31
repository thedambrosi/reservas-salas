<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CancelReservationRequest;
use App\Http\Requests\Api\IndexReservationRequest;
use App\Http\Requests\Api\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\ReservationSeriesResource;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\Reservations\BookReservationData;
use App\Services\Reservations\ReservationBooker;
use App\Services\Reservations\ReservationCanceller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationBooker $booker,
        private readonly ReservationCanceller $canceller,
    ) {}

    public function index(IndexReservationRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Reservation::class);

        $reservations = Reservation::query()
            ->with(['room', 'user'])
            ->when($request->filled('room_id'), fn ($query) => $query->where('room_id', $request->integer('room_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->boolean('mine'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($request->filled('date'), function ($query) use ($request) {
                $day = CarbonImmutable::parse($request->string('date')->toString())->startOfDay();
                // A reservation belongs to a day if its interval overlaps that day.
                $query->where('starts_at', '<', $day->addDay())
                    ->where('ends_at', '>', $day);
            })
            ->when($request->filled('from'), fn ($query) => $query->where('starts_at', '>=', CarbonImmutable::parse($request->string('from')->toString())->startOfDay()))
            ->when($request->filled('to'), fn ($query) => $query->where('starts_at', '<=', CarbonImmutable::parse($request->string('to')->toString())->endOfDay()))
            ->orderBy('starts_at')
            ->paginate($request->integer('per_page') ?: 15)
            ->withQueryString();

        return ReservationResource::collection($reservations);
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $room = Room::findOrFail($request->integer('room_id'));
        $responsible = $this->resolveResponsible($request->responsibleUserId(), $actor);

        $reservations = $this->booker->book(new BookReservationData(
            room: $room,
            responsible: $responsible,
            startsAt: $request->startsAt(),
            endsAt: $request->endsAt(),
            recurrence: $request->recurrenceRule(),
        ));

        $series = $reservations->first()?->series;

        $resource = $series !== null
            ? ReservationSeriesResource::make($series->load(['reservations.room', 'reservations.user']))
            : ReservationResource::make($reservations->first()->load(['room', 'user']));

        return $resource->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Reservation $reservation): ReservationResource
    {
        $this->authorize('view', $reservation);

        return ReservationResource::make($reservation->load(['room', 'user']));
    }

    public function cancel(CancelReservationRequest $request, Reservation $reservation): JsonResource|JsonResponse
    {
        abort_if($reservation->isCancelled(), Response::HTTP_CONFLICT, 'A reserva já está cancelada.');

        /** @var User $actor */
        $actor = $request->user();

        if ($request->scope() === 'series') {
            abort_if($reservation->reservation_series_id === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'Esta reserva não faz parte de uma série recorrente.');

            $cancelled = $this->canceller->cancelSeries($reservation->series, $actor, $request->reason());

            return response()->json([
                'message' => "{$cancelled} ocorrência(s) futura(s) da série foram canceladas.",
                'cancelled_count' => $cancelled,
            ]);
        }

        $this->canceller->cancelOccurrence($reservation, $actor, $request->reason());

        return ReservationResource::make($reservation->fresh()->load(['room', 'user']));
    }

    private function resolveResponsible(?int $responsibleUserId, User $actor): User
    {
        if ($responsibleUserId === null || $responsibleUserId === $actor->id) {
            return $actor;
        }

        return User::findOrFail($responsibleUserId);
    }
}
