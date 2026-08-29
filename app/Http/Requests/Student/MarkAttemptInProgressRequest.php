<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class MarkAttemptInProgressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $attempt = $this->route('attempt');

        return $attempt->student_id === $this->user()->id && $attempt->isOpen();
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
