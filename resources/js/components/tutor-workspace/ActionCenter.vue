<script setup>
import {
    BellAlertIcon,
    CalendarIcon,
    ClipboardDocumentCheckIcon,
    FolderOpenIcon,
    InboxArrowDownIcon,
    UserGroupIcon,
    VideoCameraIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    items: { type: Array, default: () => [] },
})

const ICONS = {
    session_starting_soon: VideoCameraIcon,
    booking_request: InboxArrowDownIcon,
    pending_review: ClipboardDocumentCheckIcon,
    content_release: FolderOpenIcon,
    student_attention: UserGroupIcon,
}
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Action Center</h2>

        <div v-if="items.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <BellAlertIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-gray-500">Nothing needs your attention right now.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-gray-100">
            <li v-for="(item, index) in items" :key="index" class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div class="flex items-center gap-3">
                    <span class="bg-amber/10 text-amber flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                        <component :is="ICONS[item.type] ?? CalendarIcon" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-ink font-semibold">{{ item.title }}</p>
                        <p class="text-sm text-gray-500">{{ item.subtitle }}</p>
                    </div>
                </div>
                <router-link :to="item.url" class="text-accent shrink-0 text-sm font-semibold whitespace-nowrap">
                    {{ item.action_label }}
                </router-link>
            </li>
        </ul>
    </div>
</template>
