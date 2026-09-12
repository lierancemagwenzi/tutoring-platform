<script setup>
import { reactive, ref } from 'vue'
import { useSelfPacedCoursesStore } from '../../stores/selfPacedCourses'
import FloatingLabelInput from '../forms/FloatingLabelInput.vue'
import SelectInput from '../forms/SelectInput.vue'

// ZAR is the only currency PayFast (the sole payment gateway) can settle —
// see OrderService's currency checks — so it's the only option here too.
const CURRENCY_OPTIONS = [{ value: 'ZAR', label: 'ZAR' }]

const props = defineProps({
    course: { type: Object, required: true },
})

const emit = defineEmits(['updated'])

const store = useSelfPacedCoursesStore()
const saving = ref(false)
const error = ref('')
const success = ref(false)

const form = reactive({
    price: props.course.price ?? '',
    currency: props.course.currency ?? 'ZAR',
})

async function save() {
    saving.value = true
    error.value = ''
    success.value = false

    try {
        const updated = await store.updateCourse(props.course.id, {
            price: form.price === '' ? null : Number(form.price),
            currency: form.currency || null,
        })
        emit('updated', updated)
        success.value = true
    } catch (err) {
        const errors = err.response?.data?.errors
        error.value = errors ? Object.values(errors).flat().join(' ') : (err.response?.data?.message ?? 'Something went wrong.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <form class="max-w-md space-y-4 rounded-2xl bg-card p-6 shadow-elevated" novalidate @submit.prevent="save">
        <h2 class="text-body text-lg font-bold">Pricing</h2>

        <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>
        <p v-if="success" class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">Saved.</p>

        <div class="grid grid-cols-2 gap-4">
            <FloatingLabelInput id="course-price" v-model="form.price" type="number" label="Price" />
            <SelectInput id="course-currency" v-model="form.currency" label="Currency" :options="CURRENCY_OPTIONS" />
        </div>
        <p class="text-xs text-muted">A price and currency must be set before this course can be published.</p>

        <div class="flex justify-end pt-2">
            <button
                type="submit"
                :disabled="saving"
                class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
                {{ saving ? 'Saving…' : 'Save Pricing' }}
            </button>
        </div>
    </form>
</template>
