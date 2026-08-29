<script setup>
defineProps({
    activity: { type: Object, required: true },
})

const emit = defineEmits(['auto-complete'])
</script>

<template>
    <div class="space-y-4">
        <p v-if="!activity.attachments?.length" class="text-sm text-gray-500">No audio has been added yet.</p>
        <div v-for="attachment in activity.attachments ?? []" :key="attachment.id">
            <p v-if="attachment.title" class="mb-1.5 text-sm font-semibold text-gray-700">{{ attachment.title }}</p>
            <audio :src="attachment.url" controls class="w-full" @ended="emit('auto-complete')" />
        </div>
    </div>
</template>
