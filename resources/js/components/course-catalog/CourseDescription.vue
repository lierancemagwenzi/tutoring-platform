<script setup>
defineProps({
    course: { type: Object, required: true },
})
</script>

<template>
    <div v-if="course.description || course.promo_description" class="bg-card shadow-elevated rounded-2xl p-6">
        <h2 class="text-body text-lg font-bold">About this course</h2>
        <div v-if="course.promo_description" class="prose prose-sm text-muted mt-3 max-w-none" v-html="course.promo_description" />
        <div v-if="course.description" class="prose prose-sm text-muted mt-3 max-w-none" v-html="course.description" />
    </div>
</template>
