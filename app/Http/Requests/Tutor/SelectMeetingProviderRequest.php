<?php

namespace App\Http\Requests\Tutor;

use App\Enums\ConnectedAccountProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SelectMeetingProviderRequest extends FormRequest
{
    /**
     * Providers with a real MeetingProviderContract implementation this
     * phase — kept separate from ConnectedAccountProvider's full case list
     * so a future connectable-but-not-yet-a-meeting-provider addition
     * doesn't silently become selectable here.
     *
     * @var list<ConnectedAccountProvider>
     */
    public const IMPLEMENTED = [ConnectedAccountProvider::Google];

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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $provider = ConnectedAccountProvider::tryFrom((string) $this->input('provider'));

            if (! $provider || ! in_array($provider, self::IMPLEMENTED, true)) {
                $validator->errors()->add('provider', 'This meeting provider is not available yet.');

                return;
            }

            $connected = $this->user()->tutorProfile
                ->connectedAccounts()
                ->where('provider', $provider)
                ->exists();

            if (! $connected) {
                $validator->errors()->add('provider', 'Connect your Google account in Connected Accounts before selecting it as a meeting provider.');
            }
        });
    }
}
