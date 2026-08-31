<?php

namespace App\Exceptions;

use App\Services\Reservations\ReservationConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ReservationConflictException extends RuntimeException
{
    /**
     * @param  list<ReservationConflict>  $conflicts
     */
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct('The requested time slot is not available for this room.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'starts_at' => [$this->getMessage()],
            ],
            'conflicts' => array_map(fn (ReservationConflict $conflict) => $conflict->toArray(), $this->conflicts),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
