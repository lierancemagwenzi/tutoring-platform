<?php

namespace App\Services\Admin;

/**
 * Reports whether each third-party integration is configured — derived
 * live from actual config() values, never hardcoded, and never exposing
 * the values themselves (only configured / needs_configuration /
 * coming_soon).
 */
class IntegrationStatusService
{
    /**
     * @return array<string, array{name: string, status: string}>
     */
    public function status(): array
    {
        return [
            'payfast' => ['name' => 'PayFast', 'status' => $this->boolStatus($this->payfastConfigured())],
            'google' => ['name' => 'Google OAuth', 'status' => $this->boolStatus($this->googleConfigured())],
            'h5p' => ['name' => 'H5P', 'status' => $this->boolStatus($this->h5pConfigured())],
            // SurveyJS is a client-side-only library here — there is no
            // server-side credential or config key for it to be missing.
            'surveyjs' => ['name' => 'SurveyJS', 'status' => 'configured'],
            'email' => ['name' => 'Email', 'status' => $this->boolStatus($this->emailConfigured())],
        ];
    }

    public function payfastConfigured(): bool
    {
        return filled(config('services.payfast.merchant_id'))
            && filled(config('services.payfast.merchant_key'))
            && filled(config('services.payfast.passphrase'));
    }

    public function googleConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function h5pConfigured(): bool
    {
        return filled(config('services.h5p.url')) && filled(config('services.h5p.key'));
    }

    public function emailConfigured(): bool
    {
        $mailer = config('mail.default');

        if (in_array($mailer, ['log', 'array', null], true)) {
            return false;
        }

        if ($mailer === 'smtp') {
            return filled(config('mail.mailers.smtp.host')) && filled(config('mail.mailers.smtp.username'));
        }

        return true;
    }

    private function boolStatus(bool $configured): string
    {
        return $configured ? 'configured' : 'needs_configuration';
    }
}
