// Shared date/time formatting for the Learning Hub, where the API returns
// ISO-8601 timestamps (unlike Booking's pre-formatted date/time strings).

export function formatDate(value) {
    if (!value) return null
    return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

export function formatTime(value) {
    if (!value) return null
    return new Date(value).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
}

export function formatDateTime(value) {
    if (!value) return null
    return `${formatDate(value)} · ${formatTime(value)}`
}

export function formatRelativeTime(value) {
    if (!value) return null

    const diffMs = new Date(value).getTime() - Date.now()
    const diffMinutes = Math.round(diffMs / 60000)

    const thresholds = [
        [60, 'minute', diffMinutes],
        [1440, 'hour', Math.round(diffMinutes / 60)],
        [43200, 'day', Math.round(diffMinutes / 1440)],
        [Infinity, 'month', Math.round(diffMinutes / 43200)],
    ]

    const [, unit, amount] = thresholds.find(([limit]) => Math.abs(diffMinutes) < limit)
    const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })
    return formatter.format(amount, unit)
}
