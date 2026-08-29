// Formats a list of grade levels (e.g. [8, 9, 10, 12]) into a compact, human-readable
// string, collapsing consecutive runs into ranges: "8–10, 12".
export function formatGradeLevels(levels) {
    const sorted = [...levels].sort((a, b) => a - b)
    const parts = []
    let start = sorted[0]
    let end = sorted[0]

    for (let i = 1; i <= sorted.length; i++) {
        const current = sorted[i]
        if (current === end + 1) {
            end = current
            continue
        }

        parts.push(start === end ? `${start}` : `${start}–${end}`)
        start = current
        end = current
    }

    return parts.join(', ')
}
