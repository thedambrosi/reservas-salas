<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'room_id',
        'user_id',
        'reservation_series_id',
        'starts_at',
        'ends_at',
        'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
        'status' => ReservationStatus::class,
    ];

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * The person responsible for the reservation.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ReservationSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(ReservationSeries::class, 'reservation_series_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === ReservationStatus::Cancelled;
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', ReservationStatus::Confirmed);
    }
}
