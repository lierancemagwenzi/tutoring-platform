<?php

namespace App\Services\TutorWorkspace;

use App\Models\Booking;
use App\Models\TeachingSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Merges the outputs the other Tutor Workspace services already computed
 * into a single, urgency-sorted action list. This is purely a composition
 * step — it takes already-fetched data and never re-queries, so nothing
 * here duplicates the domain services' own logic.
 */
class ActionCenterService
{
    /**
     * @param  list<array<string, mixed>>  $pendingReviews
     * @param  Collection<int, Booking>  $bookingRequests
     * @param  Collection<int, TeachingSession>  $sessionsStartingSoon
     * @param  list<array<string, mixed>>  $contentReleaseItems
     * @param  list<array<string, mixed>>  $studentsRequiringAttention
     * @return list<array<string, mixed>>
     */
    public function build(
        array $pendingReviews,
        Collection $bookingRequests,
        Collection $sessionsStartingSoon,
        array $contentReleaseItems,
        array $studentsRequiringAttention,
    ): array {
        $items = collect();

        foreach ($sessionsStartingSoon as $session) {
            $items->push([
                'type' => 'session_starting_soon',
                'title' => 'Session starting soon',
                'subtitle' => "{$session->service->title} at {$session->start_time}",
                'action_label' => $session->sessionMeeting ? 'Join Meeting' : 'Open Session',
                'url' => "/tutor/sessions/{$session->id}",
                'urgency' => 0,
                'timestamp' => Carbon::parse($session->date->format('Y-m-d').' '.$session->start_time),
            ]);
        }

        foreach ($bookingRequests as $booking) {
            $items->push([
                'type' => 'booking_request',
                'title' => 'New booking request',
                'subtitle' => trim("{$booking->student->first_name} {$booking->student->last_name}")." · {$booking->service->title}",
                'action_label' => 'Accept Booking',
                'url' => '/tutor/booking-requests',
                'urgency' => 1,
                'timestamp' => $booking->created_at,
            ]);
        }

        foreach ($pendingReviews as $review) {
            $items->push([
                'type' => 'pending_review',
                'title' => $review['status'] === 'under_review' ? 'Continue reviewing' : 'Review submission',
                'subtitle' => "{$review['student_name']} · {$review['title']}",
                'action_label' => 'Review',
                'url' => $review['url'],
                'urgency' => 2,
                'timestamp' => $review['submitted_at'],
            ]);
        }

        foreach ($contentReleaseItems as $item) {
            $items->push([
                'type' => 'content_release',
                'title' => $item['reason_label'],
                'subtitle' => "{$item['lesson_title']} · {$item['title']}",
                'action_label' => $item['reason'] === 'manual_release_pending' ? 'Release Content' : 'Edit Availability',
                'url' => $item['manage_url'],
                'urgency' => 3,
                'timestamp' => Carbon::now(),
            ]);
        }

        // Only surface a flag here if it links somewhere concrete — there is
        // no student profile page to fall back to, so a reason with no
        // record behind it (e.g. "No recent activity") stays in the
        // Students Requiring Attention widget only, not the Action Center.
        $actionableFlags = collect($studentsRequiringAttention)
            ->map(fn (array $flag) => [...$flag, 'reason' => collect($flag['reasons'])->first(fn (array $r) => $r['url'] !== null)])
            ->filter(fn (array $flag) => $flag['reason'] !== null)
            ->take(3);

        foreach ($actionableFlags as $flag) {
            $items->push([
                'type' => 'student_attention',
                'title' => $flag['reason']['label'],
                'subtitle' => $flag['student_name'],
                'action_label' => 'View Student',
                'url' => $flag['reason']['url'],
                'urgency' => 4,
                'timestamp' => Carbon::now(),
            ]);
        }

        return $items->sortBy('urgency')->values()->all();
    }
}
