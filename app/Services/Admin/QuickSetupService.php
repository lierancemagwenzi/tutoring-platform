<?php

namespace App\Services\Admin;

use App\Models\Subject;

/**
 * The onboarding/configuration checklist shown on the Admin Dashboard.
 * This is a status layer only — every item links to the real
 * configuration screen responsible for it (Settings, Subjects,
 * Integrations); nothing here duplicates those systems.
 */
class QuickSetupService
{
    public function __construct(
        private readonly PlatformSettingService $settings,
        private readonly IntegrationStatusService $integrations,
    ) {}

    /**
     * @return list<array{key: string, name: string, description: string, status: string, required: bool, action_label: string, action_link: string}>
     */
    public function checklist(): array
    {
        $integrationStatus = $this->integrations->status();

        return [
            $this->item(
                key: 'platform',
                name: 'Platform Configuration',
                description: 'Platform name, description, timezone, currency, and contact email.',
                status: filled($this->settings->get('general.platform_name')) && filled($this->settings->get('general.contact_email')) ? 'complete' : 'needs_attention',
                required: true,
                actionLabel: 'Configure Platform',
                actionLink: '/admin/settings/general',
            ),
            $this->item(
                key: 'subjects',
                name: 'Subjects',
                description: 'At least one active subject must exist before tutors can offer anything.',
                status: Subject::active()->exists() ? 'complete' : 'not_configured',
                required: true,
                actionLabel: 'Configure Subjects',
                actionLink: '/admin/subjects',
            ),
            $this->item(
                key: 'email',
                name: 'Email',
                description: 'SMTP configuration used for OTP verification, password resets, and notifications.',
                status: $integrationStatus['email']['status'] === 'configured' ? 'complete' : 'not_configured',
                required: true,
                actionLabel: 'Configure Email',
                actionLink: '/admin/settings/email',
            ),
            $this->item(
                key: 'payments',
                name: 'Payments',
                description: 'PayFast merchant configuration for processing bookings and course purchases.',
                status: $integrationStatus['payfast']['status'] === 'configured' ? 'complete' : 'not_configured',
                required: true,
                actionLabel: 'Configure PayFast',
                actionLink: '/admin/settings/payments',
            ),
            $this->item(
                key: 'authentication',
                name: 'Authentication',
                description: 'Student/tutor registration, email verification, and password reset.',
                status: $this->settings->get('auth.registration_enabled') && $this->settings->get('auth.email_verification_enabled') ? 'complete' : 'needs_attention',
                required: true,
                actionLabel: 'Configure Authentication',
                actionLink: '/admin/settings/authentication',
            ),
            $this->item(
                key: 'certificates',
                name: 'Certificate Generation',
                description: 'PDF certificate generation for completed self-paced courses.',
                status: $this->settings->get('courses.certificates_enabled') ? 'complete' : 'not_configured',
                required: true,
                actionLabel: 'Configure Courses',
                actionLink: '/admin/settings/courses',
            ),
            $this->item(
                key: 'self_paced_integrations',
                name: 'Self-Paced Course Integrations',
                description: 'H5P and SurveyJS, used by self-paced course assessments.',
                status: $integrationStatus['h5p']['status'] === 'configured' ? 'complete' : 'needs_attention',
                required: false,
                actionLabel: 'View Integrations',
                actionLink: '/admin/integrations',
            ),
            $this->item(
                key: 'tutoring',
                name: 'Tutoring Configuration',
                description: 'Tutor availability, booking, and session management — built-in, no external configuration required.',
                status: 'complete',
                required: false,
                actionLabel: 'View Bookings',
                actionLink: '/admin/bookings',
            ),
            $this->item(
                key: 'google_meet',
                name: 'Google Meet',
                description: 'Google OAuth for tutors who want to auto-generate Meet links for sessions.',
                status: $integrationStatus['google']['status'] === 'configured' ? 'complete' : 'needs_attention',
                required: false,
                actionLabel: 'View Integrations',
                actionLink: '/admin/integrations',
            ),
        ];
    }

    /**
     * @return array{completed: int, total: int, required_completed: int, required_total: int, ready_for_production: bool}
     */
    public function progress(): array
    {
        $items = $this->checklist();
        $required = array_filter($items, fn ($item) => $item['required']);

        $completed = count(array_filter($items, fn ($item) => $item['status'] === 'complete'));
        $requiredCompleted = count(array_filter($required, fn ($item) => $item['status'] === 'complete'));

        return [
            'completed' => $completed,
            'total' => count($items),
            'required_completed' => $requiredCompleted,
            'required_total' => count($required),
            'ready_for_production' => $requiredCompleted === count($required),
        ];
    }

    /**
     * @return array{key: string, name: string, description: string, status: string, required: bool, action_label: string, action_link: string}
     */
    private function item(string $key, string $name, string $description, string $status, bool $required, string $actionLabel, string $actionLink): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'status' => $status,
            'required' => $required,
            'action_label' => $actionLabel,
            'action_link' => $actionLink,
        ];
    }
}
