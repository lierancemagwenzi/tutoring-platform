<script setup>
defineProps({
    service: { type: Object, required: true },
})

const emit = defineEmits(['continue'])
</script>

<template>
    <div class="bg-card shadow-elevated flex flex-col rounded-2xl p-5">
        <p class="text-muted text-sm">{{ service.subject.name }} &middot; {{ service.category.name }}</p>
        <p class="text-body mt-1 font-bold">{{ service.title }}</p>
        <p class="text-muted mt-2 text-sm">{{ service.description }}</p>

        <p class="text-body mt-3 text-lg font-bold">{{ service.currency }} {{ service.price }}</p>

        <dl class="text-muted mt-3 space-y-1 text-sm">
            <div class="flex justify-between">
                <dt>Session format</dt>
                <dd>{{ service.session_format.name }}</dd>
            </div>
            <div class="flex justify-between">
                <dt>Duration</dt>
                <dd>{{ service.session_duration_minutes }} min</dd>
            </div>
            <div class="flex justify-between">
                <dt>Sessions included</dt>
                <dd>{{ service.sessions_included }}</dd>
            </div>
            <div class="flex justify-between">
                <dt>Max students</dt>
                <dd>{{ service.max_students_per_session }}</dd>
            </div>
            <div class="flex justify-between">
                <dt>Validity</dt>
                <dd>{{ service.validity_period_days }} days</dd>
            </div>
        </dl>

        <div v-if="service.learning_resources.length" class="mt-4">
            <p class="text-muted text-xs font-semibold tracking-wide uppercase">Learning Resources</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <span
                    v-for="resource in service.learning_resources"
                    :key="resource.id"
                    class="bg-accent/10 text-accent rounded-full px-3 py-1 text-xs font-medium"
                >
                    {{ resource.name }}
                </span>
            </div>
        </div>

        <div v-if="service.assessment_types.length" class="mt-4">
            <p class="text-muted text-xs font-semibold tracking-wide uppercase">Assessments</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <span
                    v-for="type in service.assessment_types"
                    :key="type.id"
                    class="bg-card-alt text-body rounded-full px-3 py-1 text-xs font-medium"
                >
                    {{ type.name }}
                </span>
            </div>
        </div>

        <div v-if="service.curricula.length" class="mt-4">
            <p class="text-muted text-xs font-semibold tracking-wide uppercase">Curriculum</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <span
                    v-for="curriculum in service.curricula"
                    :key="curriculum.id"
                    class="bg-card-alt text-body rounded-full px-3 py-1 text-xs font-medium"
                >
                    {{ curriculum.name }}
                </span>
            </div>
        </div>

        <button
            type="button"
            class="bg-amber shadow-elevated mt-5 rounded-full px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95"
            @click="emit('continue')"
        >
            Book This Service
        </button>
    </div>
</template>
