<script setup>
import { reactive } from 'vue'
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline'

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

/**
 * A YouTube/Vimeo attachment stores the page URL the Insider pasted, which a
 * <video> element can't play — turn it into the provider's embeddable player
 * URL. Returns null if the URL isn't recognised, so the caller falls back to
 * a plain "open" link (the same thing the Insider's preview does).
 */
function embedUrl(attachment) {
    let url
    try {
        url = new URL(attachment.url)
    } catch {
        return null
    }
    const host = url.hostname.replace(/^(www\.|m\.)/, '')

    if (attachment.media_type === 'video_youtube') {
        let id = null
        if (host === 'youtu.be') id = url.pathname.slice(1)
        else if (url.searchParams.get('v')) id = url.searchParams.get('v')
        else id = url.pathname.match(/^\/(?:embed|shorts|live)\/([^/?]+)/)?.[1] ?? null

        return id ? `https://www.youtube-nocookie.com/embed/${id.split('/')[0]}` : null
    }

    if (attachment.media_type === 'video_vimeo') {
        // vimeo.com/123, vimeo.com/123/<privacy hash>, player.vimeo.com/video/123
        const [, id, hash] = url.pathname.match(/(?:\/video)?\/(\d+)(?:\/([0-9a-f]+))?/) ?? []
        if (!id) return null

        return `https://player.vimeo.com/video/${id}${hash ? `?h=${hash}` : ''}`
    }

    return null
}

function isEmbed(attachment) {
    return ['video_youtube', 'video_vimeo'].includes(attachment.media_type)
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
