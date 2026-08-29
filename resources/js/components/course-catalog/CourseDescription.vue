<script setup>
defineProps({
    course: { type: Object, required: true },
})
</script>

<template>
    <div v-if="course.description || course.promo_description" class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">About this course</h2>
        <div v-if="course.promo_description" class="prose prose-sm mt-3 max-w-none text-gray-600" v-html="course.promo_description" />
        <div v-if="course.description" class="prose prose-sm mt-3 max-w-none text-gray-600" v-html="course.description" />
    </div>
</template>
