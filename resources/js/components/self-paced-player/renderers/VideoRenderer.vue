<script setup>
// Auto-completes on native playback end — the one concrete, unambiguous
// "viewed" signal available without a backend-configured auto-complete
// flag (SelfPacedActivity has no such column; see the plan's "Real gaps"
// section). Every other simple activity type requires an explicit Mark
// Complete click instead.
defineProps({
    activity: { type: Object, required: true },
})

const emit = defineEmits(['auto-complete'])
</script>

<template>
    <div class="space-y-4">
        <p v-if="!activity.attachments?.length" class="text-sm text-gray-500">No video has been added yet.</p>
        <video
            v-for="attachment in activity.attachments ?? []"
            :key="attachment.id"
            :src="attachment.url"
            controls
            class="w-full rounded-xl bg-black"
            @ended="emit('auto-complete')"
        />
    </div>
</template>
