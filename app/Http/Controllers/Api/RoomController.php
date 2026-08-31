<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRoomRequest;
use App\Http\Requests\Api\StoreRoomRequest;
use App\Http\Requests\Api\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class RoomController extends Controller
{
    public function index(IndexRoomRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Room::class);

        $rooms = Room::query()
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->when($request->integer('min_capacity') > 0, fn ($query) => $query->where('capacity', '>=', $request->integer('min_capacity')))
            ->orderBy('name')
            ->paginate($request->integer('per_page') ?: 15)
            ->withQueryString();

        return RoomResource::collection($rooms);
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = Room::create($request->validated());

        return RoomResource::make($room)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Room $room): RoomResource
    {
        $this->authorize('view', $room);

        return RoomResource::make($room);
    }

    public function update(UpdateRoomRequest $request, Room $room): RoomResource
    {
        $room->update($request->validated());

        return RoomResource::make($room);
    }

    public function destroy(Room $room): JsonResponse
    {
        $this->authorize('delete', $room);

        $hasUpcoming = $room->reservations()
            ->confirmed()
            ->where('starts_at', '>=', CarbonImmutable::now())
            ->exists();

        abort_if($hasUpcoming, Response::HTTP_CONFLICT, 'A sala possui reservas futuras confirmadas e não pode ser removida.');

        $room->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
