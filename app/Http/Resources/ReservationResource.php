<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'user_id' => $this->user_id,
            'series_id' => $this->reservation_series_id,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'status' => $this->status->value,
            'is_recurring' => $this->reservation_series_id !== null,
            'cancellation' => $this->when($this->isCancelled(), fn () => [
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
                'cancelled_by' => $this->cancelled_by,
                'reason' => $this->cancellation_reason,
            ]),
            'room' => RoomResource::make($this->whenLoaded('room')),
            'responsible' => UserResource::make($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
