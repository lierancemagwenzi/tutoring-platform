<script setup>
import { BellIcon } from '@heroicons/vue/24/outline'
import { formatRelativeTime } from '../../utils/formatDateTime'

defineProps({
    notifications: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Notifications</h2>

        <div v-if="notifications.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <BellIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-gray-500">You're all caught up — no new notifications.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-gray-100">
            <li v-for="(item, index) in notifications" :key="index" class="py-3 first:pt-0 last:pb-0">
                <component :is="item.url ? 'router-link' : 'div'" :to="item.url" class="flex items-start gap-3">
                    <span class="bg-amber/10 text-amber mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full">
                        <BellIcon class="h-4 w-4" />
                    </span>
                    <div>
                        <p class="text-ink text-sm font-medium">{{ item.message }}</p>
                        <p class="text-xs text-gray-400">{{ formatRelativeTime(item.date) }}</p>
                    </div>
                </component>
            </li>
        </ul>
    </div>
</template>
