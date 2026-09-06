<script setup>
import { reactive, ref } from 'vue'
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { useSelfPacedCoursesStore } from '../../stores/selfPacedCourses'

const props = defineProps({
    course: { type: Object, required: true },
})

const emit = defineEmits(['updated'])

const store = useSelfPacedCoursesStore()
const saving = ref(false)
const error = ref('')
const success = ref(false)

const form = reactive({
    learning_objectives: [...(props.course.learning_objectives ?? [])],
    prerequisites: [...(props.course.prerequisites ?? [])],
    target_audience: [...(props.course.target_audience ?? [])],
})

function addItem(field) {
    form[field].push('')
}

function removeItem(field, index) {
    form[field].splice(index, 1)
}

async function save() {
    saving.value = true
    error.value = ''
    success.value = false

    try {
        const updated = await store.updateCourse(props.course.id, {
            learning_objectives: form.learning_objectives.filter((item) => item.trim() !== ''),
            prerequisites: form.prerequisites.filter((item) => item.trim() !== ''),
            target_audience: form.target_audience.filter((item) => item.trim() !== ''),
        })
        emit('updated', updated)
        success.value = true
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Something went wrong.'
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <form class="max-w-2xl space-y-6 rounded-2xl bg-card p-6 shadow-elevated" novalidate @submit.prevent="save">
        <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>
        <p v-if="success" class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">Saved.</p>

        <div v-for="[field, label] in [['learning_objectives', 'Learning Objectives'], ['prerequisites', 'Prerequisites'], ['target_audience', 'Target Audience']]" :key="field">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-body">{{ label }}</p>
                <button type="button" class="text-accent flex items-center gap-1 text-xs font-semibold" @click="addItem(field)">
                    <PlusIcon class="h-3.5 w-3.5" /> Add
                </button>
            </div>
            <div v-if="form[field].length === 0" class="mt-2 text-sm text-muted italic">None added yet.</div>
            <div v-for="(item, index) in form[field]" :key="index" class="mt-2 flex items-center gap-2">
                <input
                    v-model="form[field][index]"
                    type="text"
                    class="w-full rounded-xl border border-border px-4 py-2.5 text-sm text-body outline-none"
                />
                <button type="button" class="text-muted hover:text-red-600" @click="removeItem(field, index)">
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button
                type="submit"
                :disabled="saving"
                class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
                {{ saving ? 'Saving…' : 'Save Changes' }}
            </button>
        </div>
    </form>
</template>
