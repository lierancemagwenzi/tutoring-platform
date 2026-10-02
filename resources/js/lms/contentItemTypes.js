import { CalculatorIcon, DocumentTextIcon, PaperClipIcon, ShareIcon } from '@heroicons/vue/24/outline'

// The kinds of item a Content block / Content activity can hold
// (mirrors App\Support\ContentItems::TYPES).
export const CONTENT_ITEM_TYPES = {
    rich_text: { label: 'Text', icon: DocumentTextIcon },
    math: { label: 'Maths', icon: CalculatorIcon },
    mermaid: { label: 'Diagram', icon: ShareIcon },
    media: { label: 'File / Video', icon: PaperClipIcon },
}

const NOUNS = { rich_text: ['text', 'texts'], math: ['formula', 'formulas'], mermaid: ['diagram', 'diagrams'], media: ['file', 'files'] }

/** e.g. "2 diagrams · 1 text · 3 files" */
export function summariseContentItems(items) {
    if (!items?.length) return 'Empty'
    const counts = {}
    for (const item of items) counts[item.type] = (counts[item.type] ?? 0) + 1

    return Object.entries(counts)
        .map(([type, count]) => `${count} ${NOUNS[type]?.[count === 1 ? 0 : 1] ?? type}`)
        .join(' · ')
}

/** The comparable shape of an item list — leaves out the rich-text editor's derived JSON. */
export function contentItemsSnapshot(items) {
    return JSON.stringify(items.map(({ json, ...rest }) => rest))
}
