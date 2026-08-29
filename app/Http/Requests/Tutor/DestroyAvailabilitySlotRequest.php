<?php

namespace App\Http\Requests\Tutor;

use App\Models\AvailabilitySlot;
use Illuminate\Foundation\Http\FormRequest;

class DestroyAvailabilitySlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $slot = $this->route('slot');

        if ($slot instanceof AvailabilitySlot) {
            return $slot->availabilityDate->tutor_profile_id === $this->user()->tutorProfile?->id;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
