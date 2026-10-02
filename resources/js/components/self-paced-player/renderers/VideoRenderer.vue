<script setup>
import { reactive } from 'vue'
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline'
import { EMBEDDED_VIDEO_TYPES, videoEmbedUrl } from '../../../utils/videoEmbed'

// Auto-completes on native playback end — the one concrete, unambiguous
// "viewed" signal available without a backend-configured auto-complete
// flag (SelfPacedActivity has no such column; see the plan's "Real gaps"
// section). Every other simple activity type requires an explicit Mark
// Complete click instead. Embedded YouTube/Vimeo players don't expose that
// event without their JS APIs, so those rely on Mark Complete too.
defineProps({
    activity: { type: Object, required: true },
})

const emit = defineEmits(['auto-complete'])

// Attachment ids whose file the browser couldn't play inline (e.g. AVI, or a
// MOV with an unsupported codec — both are accepted uploads).
const unplayable = reactive(new Set())

function embedUrl(attachment) {
    return videoEmbedUrl(attachment.media_type, attachment.url)
}

function isEmbed(attachment) {
    return EMBEDDED_VIDEO_TYPES.includes(attachment.media_type)
}
</script>

<template>
    <div class="space-y-6">
        <p v-if="!activity.attachments?.length" class="text-sm text-gray-500">No video has been added yet.</p>

        <div v-for="attachment in activity.attachments ?? []" :key="attachment.id" class="space-y-2">
            <template v-if="isEmbed(attachment)">
                <div v-if="embedUrl(attachment)" class="aspect-video w-full overflow-hidden rounded-xl bg-black">
                    <iframe
                        :src="embedUrl(attachment)"
                        :title="attachment.title || 'Video'"
                        class="h-full w-full"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        allowfullscreen
                        referrerpolicy="strict-origin-when-cross-origin"
                    />
                </div>
            </template>

            <template v-else>
                <video
                    v-if="!unplayable.has(attachment.id)"
                    :src="attachment.url"
                    controls
                    preload="metadata"
                    playsinline
                    class="w-full rounded-xl bg-black"
                    @ended="emit('auto-complete')"
                    @error="unplayable.add(attachment.id)"
                />
                <p v-else class="rounded-xl bg-card-alt px-4 py-6 text-center text-sm text-muted">
                    This video can't be played in your browser. Open it in a new tab to watch or download it.
                </p>
            </template>

            <a
                :href="attachment.url"
                target="_blank"
                rel="noopener"
                class="text-accent inline-flex items-center gap-1.5 text-sm font-semibold"
            >
                <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                Open {{ attachment.title || 'video' }} in a new tab
            </a>
        </div>
    </div>
</template>
