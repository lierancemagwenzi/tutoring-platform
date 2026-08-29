<script setup>
// assignment / homework / reading — plain instructions text, matching
// BlockDetail.vue's existing "learning activity" fallback pattern.
defineProps({
    activity: { type: Object, required: true },
})
</script>

<template>
    <div>
        <!-- Plain textarea-authored text (not rich text) — preserve line
             breaks without interpreting it as HTML. -->
        <p v-if="activity.content?.instructions" class="text-sm whitespace-pre-line text-gray-700">
            {{ activity.content.instructions }}
        </p>
        <p v-else class="text-sm text-gray-500">No instructions have been added yet.</p>
    </div>
</template>
