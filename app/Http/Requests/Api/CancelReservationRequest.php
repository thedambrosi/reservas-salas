<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->route('reservation')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['nullable', 'in:occurrence,series'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function scope(): string
    {
        return $this->input('scope', 'occurrence');
    }

    public function reason(): ?string
    {
        return $this->input('reason');
    }
}
