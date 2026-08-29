<script setup>
import { FolderOpenIcon } from '@heroicons/vue/24/outline'

defineProps({
    items: { type: Array, default: () => [] },
})

function actionLabel(reason) {
    return reason === 'manual_release_pending' ? 'Release' : 'Edit Availability'
}
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Content Release</h2>

        <div v-if="items.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <FolderOpenIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-gray-500">Nothing needs releasing right now.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-gray-100">
            <li v-for="(item, index) in items" :key="index" class="py-3 first:pt-0 last:pb-0">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-ink font-semibold">{{ item.title }}</p>
                        <p class="text-sm text-gray-500">{{ item.lesson_title }}</p>
                        <span class="mt-1 inline-block rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                            {{ item.reason_label }}
                        </span>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap gap-4 text-sm font-semibold">
                    <router-link :to="item.manage_url" class="text-accent">{{ actionLabel(item.reason) }}</router-link>
                    <router-link :to="item.session_url" class="text-accent">Open Session</router-link>
                </div>
            </li>
        </ul>
    </div>
</template>
