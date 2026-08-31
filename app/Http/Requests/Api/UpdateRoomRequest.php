<?php

namespace App\Http\Requests\Api;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('room')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Room $room */
        $room = $this->route('room');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('rooms', 'name')->ignoreModel($room)],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],
        ];
    }
}
