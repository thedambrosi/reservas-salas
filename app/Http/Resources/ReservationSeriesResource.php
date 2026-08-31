<?php

namespace App\Http\Resources;

use App\Models\ReservationSeries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReservationSeries
 */
class ReservationSeriesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'frequency' => $this->frequency->value,
            'interval' => $this->repeat_interval,
            'occurrences' => $this->occurrences,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'reservations' => ReservationResource::collection($this->whenLoaded('reservations')),
        ];
    }
}
