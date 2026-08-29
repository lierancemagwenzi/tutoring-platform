<?php

namespace App\Http\Requests\Tutor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload sent by <h5p-editor>'s save() call when creating
 * new content. The nested "params" structure is arbitrary, semantics-driven
 * H5P content JSON that can't be meaningfully validated field-by-field here
 * — the H5P server is the authority on whether it's well-formed.
 */
class StoreH5pContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'library' => ['required', 'string'],
            'params' => ['required', 'array'],
            'params.params' => ['required'],
            'params.metadata' => ['required', 'array'],
        ];
    }
}
