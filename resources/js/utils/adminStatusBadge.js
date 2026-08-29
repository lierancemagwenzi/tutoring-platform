const CLASSES = {
    // Approval / lifecycle statuses (tutors, tutor subjects, subjects)
    pending: 'bg-gray-100 text-gray-600',
    approved: 'bg-green-100 text-green-700',
    active: 'bg-green-100 text-green-700',
    paid: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    suspended: 'bg-red-100 text-red-700',
    disabled: 'bg-red-100 text-red-700',
    inactive: 'bg-gray-100 text-gray-500',
    archived: 'bg-gray-100 text-gray-500',

    // Payout statuses
    eligible: 'bg-blue-100 text-blue-700',
    processing: 'bg-amber-100 text-amber-700',
    on_hold: 'bg-orange-100 text-orange-700',
    adjusted: 'bg-purple-100 text-purple-700',
    not_refunded: 'bg-gray-100 text-gray-500',
    refunded: 'bg-blue-100 text-blue-700',

    // Payment ticket statuses
    open: 'bg-gray-100 text-gray-600',
    in_review: 'bg-amber-100 text-amber-700',
    resolved: 'bg-green-100 text-green-700',

    // Integration / config statuses
    configured: 'bg-green-100 text-green-700',
    needs_configuration: 'bg-amber-100 text-amber-700',
    coming_soon: 'bg-gray-100 text-gray-500',

    // System health statuses
    healthy: 'bg-green-100 text-green-700',
    warning: 'bg-amber-100 text-amber-700',
    failed: 'bg-red-100 text-red-700',

    // Quick Setup checklist statuses
    complete: 'bg-green-100 text-green-700',
    needs_attention: 'bg-amber-100 text-amber-700',
    not_configured: 'bg-gray-100 text-gray-600',
}

export function adminStatusBadge(status) {
    return CLASSES[status] ?? 'bg-gray-100 text-gray-600'
}

export function adminStatusLabel(status) {
    return String(status ?? '')
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ')
}
