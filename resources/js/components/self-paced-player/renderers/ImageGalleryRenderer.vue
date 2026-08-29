<script setup>
defineProps({
    activity: { type: Object, required: true },
})
</script>

<template>
    <div class="space-y-4">
        <p v-if="!activity.attachments?.length" class="text-sm text-gray-500">No images have been added yet.</p>
        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <figure v-for="attachment in activity.attachments" :key="attachment.id">
                <img :src="attachment.url" :alt="attachment.title" class="w-full rounded-xl object-cover" loading="lazy" />
                <figcaption v-if="attachment.title" class="mt-1.5 text-xs text-gray-500">{{ attachment.title }}</figcaption>
            </figure>
        </div>
    </div>
</template>
