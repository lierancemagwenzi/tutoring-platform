// Shared status → badge color mapping for Learning Hub widgets (Continue
// Learning, Pending Activities, Recent Results all render the same status
// vocabulary the backend returns via LearningHubService::statusLabel()).
const STATUS_CLASSES = {
    draft: 'bg-gray-100 text-gray-600',
    started: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-blue-100 text-blue-700',
    submitted: 'bg-amber-100 text-amber-700',
    under_review: 'bg-amber-100 text-amber-700',
    returned: 'bg-red-100 text-red-700',
    graded: 'bg-green-100 text-green-700',
    completed: 'bg-green-100 text-green-700',
    abandoned: 'bg-gray-100 text-gray-500',
    timed_out: 'bg-gray-100 text-gray-500',
}

export function statusBadgeClasses(status) {
    return STATUS_CLASSES[status] ?? 'bg-gray-100 text-gray-600'
}
