<script setup>
import { computed, ref } from 'vue'
import {
    ArchiveBoxIcon,
    ArrowDownTrayIcon,
    ArrowTopRightOnSquareIcon,
    DocumentIcon,
    DocumentTextIcon,
    MusicalNoteIcon,
    PhotoIcon,
    PlayCircleIcon,
    PresentationChartBarIcon,
    TableCellsIcon,
} from '@heroicons/vue/24/outline'
import PdfPreview from './PdfPreview.vue'
import { EMBEDDED_VIDEO_TYPES, videoEmbedUrl } from '../../utils/videoEmbed'
import { formatFileSize } from '../../utils/formatFileSize'
import { learnerMediaLabel } from '../../lms/mediaTypes'

// One lesson file as a learner sees it, in a consistent frame: what it is
// (title, type, size) and what they can do with it (open, download), above
// whatever can be shown inline — a PDF reader, the video/audio player, the
// image. Office files and archives have no inline view, so the frame alone
// is the item.
const props = defineProps({
    item: { type: Object, required: true },
})

const previewFailed = ref(false)

const embedUrl = computed(() =>
    EMBEDDED_VIDEO_TYPES.includes(props.item.media_type) ? videoEmbedUrl(props.item.media_type, props.item.url) : null,
)
const kind = computed(() => {
    const type = props.item.media_type
    if (type === 'pdf') return 'pdf'
    if (type === 'image') return 'image'
    if (type === 'video_upload') return 'video'
    if (['mp3', 'wav'].includes(type)) return 'audio'
    if (EMBEDDED_VIDEO_TYPES.includes(type)) return embedUrl.value ? 'embed' : 'link'
    return 'file'
})
const isExternal = computed(() => EMBEDDED_VIDEO_TYPES.includes(props.item.media_type))
const title = computed(() => props.item.title || props.item.original_name || learnerMediaLabel(props.item.media_type))
const meta = computed(() => [learnerMediaLabel(props.item.media_type), formatFileSize(props.item.size)].filter(Boolean).join(' · '))
const icon = computed(
    () =>
        ({
            pdf: DocumentTextIcon,
            image: PhotoIcon,
            video_upload: PlayCircleIcon,
            video_youtube: PlayCircleIcon,
            video_vimeo: PlayCircleIcon,
            mp3: MusicalNoteIcon,
            wav: MusicalNoteIcon,
            zip: ArchiveBoxIcon,
            ppt: PresentationChartBarIcon,
            pptx: PresentationChartBarIcon,
            xls: TableCellsIcon,
            xlsx: TableCellsIcon,
            csv: TableCellsIcon,
        })[props.item.media_type] ?? DocumentIcon,
)
// A download name keeps the original extension; the browser only honours it
// for same-origin files, otherwise it just opens/saves the file.
const downloadName = computed(() => props.item.original_name || title.value)
</script>

<template>
    <figure class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <figcaption class="flex flex-wrap items-center gap-3 px-4 py-3">
            <span class="bg-accent/10 text-accent flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                <component :is="icon" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1 basis-40">
                <p class="truncate text-sm font-semibold text-gray-800">{{ title }}</p>
                <p class="text-xs text-gray-500">{{ meta }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <a
                    :href="item.url"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center gap-1 rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:border-accent hover:text-accent"
                >
                    <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" />
                    {{ isExternal ? `Watch on ${item.media_type === 'video_vimeo' ? 'Vimeo' : 'YouTube'}` : 'Open' }}
                </a>
                <a
                    v-if="!isExternal"
                    :href="item.url"
                    :download="downloadName"
                    class="bg-accent/10 text-accent hover:bg-accent/20 flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-semibold"
                >
                    <ArrowDownTrayIcon class="h-3.5 w-3.5" />
                    Download
                </a>
            </div>
        </figcaption>

        <div v-if="kind === 'pdf'" class="border-t border-gray-100 p-3">
            <PdfPreview v-if="!previewFailed" :url="item.url" :title="title" @failed="previewFailed = true" />
            <p v-else class="rounded-xl bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                A preview isn't available here — use Open or Download to read this PDF.
            </p>
        </div>

        <div v-else-if="kind === 'image'" class="border-t border-gray-100 bg-gray-50 p-3">
            <img :src="item.url" :alt="title" class="mx-auto max-h-[70vh] max-w-full rounded-lg" loading="lazy" />
        </div>

        <div v-else-if="kind === 'video'" class="border-t border-gray-100 bg-black">
            <video v-if="!previewFailed" :src="item.url" controls preload="metadata" playsinline class="w-full" @error="previewFailed = true" />
            <p v-else class="bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                This video can't be played in your browser — use Open or Download to watch it.
            </p>
        </div>

        <div v-else-if="kind === 'embed'" class="aspect-video w-full border-t border-gray-100 bg-black">
            <iframe
                :src="embedUrl"
                :title="title"
                class="h-full w-full"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"
            />
        </div>

        <div v-else-if="kind === 'audio'" class="border-t border-gray-100 px-4 py-3">
            <audio :src="item.url" controls class="w-full" />
        </div>
    </figure>
</template>
