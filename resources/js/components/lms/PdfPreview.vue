<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import VuePdfEmbed from 'vue-pdf-embed'
import { ArrowsPointingInIcon, ArrowsPointingOutIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

// Inline, page-at-a-time PDF reader. Kept to a bounded height by default so
// a long document doesn't push the rest of the lesson out of view; "Expand"
// lifts the limit. The viewer fetches the file from JS, which cross-origin
// storage can refuse (CORS) even when a plain link works — on any failure it
// emits `failed` so the host can fall back to its Open/Download actions.
defineProps({
    url: { type: String, required: true },
    title: { type: String, default: 'PDF' },
})

const emit = defineEmits(['failed'])

const page = ref(1)
const pageCount = ref(null)
const rendered = ref(false)
const expanded = ref(false)

// Zoom-to-fit: in the default view each page is drawn to the viewer's height
// so the whole page is visible at once (no scrolling inside the box). On
// narrow screens CSS caps it to the available width instead. Expanded view
// draws pages at full width.
const fitHeight = ref(pageFitHeight())

function pageFitHeight() {
    return Math.max(320, Math.round(window.innerHeight * 0.7) - 24)
}

function onResize() {
    fitHeight.value = pageFitHeight()
}

onMounted(() => window.addEventListener('resize', onResize))
onBeforeUnmount(() => window.removeEventListener('resize', onResize))

function onLoaded(doc) {
    pageCount.value = doc.numPages
}

function go(delta) {
    const target = page.value + delta
    if (target < 1 || (pageCount.value && target > pageCount.value)) return
    rendered.value = false
    page.value = target
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
        <div class="relative overflow-y-auto" :class="expanded ? '' : 'max-h-[70vh]'">
            <div v-if="!rendered" class="flex h-64 items-center justify-center">
                <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
            </div>
            <VuePdfEmbed
                :source="url"
                :page="page"
                :height="expanded ? undefined : fitHeight"
                class="pdf-preview"
                :class="[rendered ? '' : 'h-0 overflow-hidden', expanded ? '' : 'py-3']"
                @loaded="onLoaded"
                @rendered="rendered = true"
                @loading-failed="emit('failed')"
                @rendering-failed="emit('failed')"
            />
        </div>

        <div class="flex items-center justify-between gap-2 border-t border-gray-200 bg-white px-3 py-2 text-sm">
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="rounded-full p-1.5 text-gray-600 hover:bg-gray-100 disabled:opacity-30"
                    :disabled="page <= 1"
                    :aria-label="`Previous page of ${title}`"
                    @click="go(-1)"
                >
                    <ChevronLeftIcon class="h-4 w-4" />
                </button>
                <span class="min-w-[6.5rem] text-center text-gray-600 tabular-nums">
                    Page {{ page }}<template v-if="pageCount"> of {{ pageCount }}</template>
                </span>
                <button
                    type="button"
                    class="rounded-full p-1.5 text-gray-600 hover:bg-gray-100 disabled:opacity-30"
                    :disabled="!pageCount || page >= pageCount"
                    :aria-label="`Next page of ${title}`"
                    @click="go(1)"
                >
                    <ChevronRightIcon class="h-4 w-4" />
                </button>
            </div>

            <button type="button" class="flex items-center gap-1 font-semibold text-gray-600 hover:text-gray-900" @click="expanded = !expanded">
                <component :is="expanded ? ArrowsPointingInIcon : ArrowsPointingOutIcon" class="h-4 w-4" />
                {{ expanded ? 'Collapse' : 'Expand' }}
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Centre the page, and never let a fit-to-height page overflow a narrow screen. */
.pdf-preview :deep(.vue-pdf-embed__page) {
    width: fit-content;
    max-width: 100%;
    margin: 0 auto;
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.12);
}
.pdf-preview :deep(canvas) {
    display: block;
    max-width: 100%;
    height: auto !important;
}
</style>
