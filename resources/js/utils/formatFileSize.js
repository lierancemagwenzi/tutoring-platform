/** 1536 → "1.5 KB". Returns '' for missing sizes. */
export function formatFileSize(bytes) {
    if (!bytes && bytes !== 0) return ''
    if (bytes < 1024) return `${bytes} B`
    const units = ['KB', 'MB', 'GB']
    let value = bytes / 1024
    let unit = 0
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024
        unit++
    }
    return `${value >= 10 ? Math.round(value) : value.toFixed(1)} ${units[unit]}`
}
