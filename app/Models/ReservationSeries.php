<?php

namespace App\Models;

use App\Enums\RecurrenceFrequency;
use Database\Factories\ReservationSeriesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationSeries extends Model
{
    /** @use HasFactory<ReservationSeriesFactory> */
    use HasFactory;

    protected $table = 'reservation_series';

    /** @var list<string> */
    protected $fillable = [
        'room_id',
        'user_id',
        'frequency',
        'repeat_interval',
        'occurrences',
        'starts_at',
        'ends_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'frequency' => RecurrenceFrequency::class,
        'repeat_interval' => 'integer',
        'occurrences' => 'integer',
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
    ];

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
