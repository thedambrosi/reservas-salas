<?php

namespace App\Http\Requests\Api;

use App\Enums\RecurrenceFrequency;
use App\Models\Reservation;
use App\Services\Reservations\RecurrenceRule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Reservation::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isAdmin = (bool) $this->user()?->isAdmin();

        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],

            // Only an administrator may book on behalf of another user; everyone
            // else is limited to booking for themselves.
            'user_id' => [
                'nullable', 'integer', 'exists:users,id',
                Rule::when(! $isAdmin, Rule::in([$this->user()?->id])),
            ],

            'recurrence' => ['nullable', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', Rule::enum(RecurrenceFrequency::class)],
            'recurrence.interval' => ['nullable', 'integer', 'min:1', 'max:30'],
            'recurrence.count' => [
                'required_with:recurrence', 'integer', 'min:2',
                'max:'.config('reservations.max_occurrences'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $starts = $this->startsAt();
            $ends = $this->endsAt();

            $minLead = (int) config('reservations.min_lead_minutes');
            if ($starts->lt(CarbonImmutable::now()->addMinutes($minLead))) {
                $validator->errors()->add('starts_at', $minLead > 0
                    ? "A reserva deve começar pelo menos {$minLead} minutos no futuro."
                    : 'A reserva não pode começar no passado.');
            }

            $maxDuration = (int) config('reservations.max_duration_minutes');
            if ($starts->diffInMinutes($ends) > $maxDuration) {
                $validator->errors()->add('ends_at', "A reserva não pode durar mais de {$maxDuration} minutos.");
            }
        });
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('starts_at')->toString());
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('ends_at')->toString());
    }

    public function recurrenceRule(): ?RecurrenceRule
    {
        /** @var array{frequency: string, interval?: int|string|null, count: int|string}|null $recurrence */
        $recurrence = $this->input('recurrence');

        return $recurrence !== null ? RecurrenceRule::fromArray($recurrence) : null;
    }

    public function responsibleUserId(): ?int
    {
        $userId = $this->input('user_id');

        return $userId !== null ? (int) $userId : null;
    }
}
