<?php

namespace App\Http\Requests\Tutor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpeedTestResultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'download_mbps' => ['required', 'numeric', 'min:0', 'max:100000'],
            'upload_mbps' => ['required', 'numeric', 'min:0', 'max:100000'],
            'latency_ms' => ['required', 'numeric', 'min:0', 'max:100000'],
            'jitter_ms' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }
}
