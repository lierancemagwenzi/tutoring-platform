<script setup>
import { onMounted, reactive, ref } from 'vue'
import { PlusIcon, TicketIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useSelfPacedCoursesStore } from '../../stores/selfPacedCourses'
import Modal from '../common/Modal.vue'
import FloatingLabelInput from '../forms/FloatingLabelInput.vue'
import SelectInput from '../forms/SelectInput.vue'

const DISCOUNT_TYPE_OPTIONS = [
    { value: 'percentage', label: 'Percentage' },
    { value: 'fixed_amount', label: 'Fixed Amount' },
]

const props = defineProps({
    courseId: { type: Number, required: true },
})

const store = useSelfPacedCoursesStore()
const loading = ref(true)
const codes = ref([])
const modalOpen = ref(false)
const saving = ref(false)
const error = ref('')

const form = reactive({
    code: '',
    discount_type: 'percentage',
    discount_value: '',
    max_redemptions: '',
});

async function load() {
    loading.value = true
    codes.value = await store.fetchDiscountCodes(props.courseId)
    loading.value = false
}

onMounted(load)

function openCreate() {
    form.code = ''
    form.discount_type = 'percentage'
    form.discount_value = ''
    form.max_redemptions = ''
    error.value = ''
    modalOpen.value = true
}

async function save() {
    saving.value = true
    error.value = ''

    try {
        const created = await store.createDiscountCode(props.courseId, {
            code: form.code,
            discount_type: form.discount_type,
            discount_value: Number(form.discount_value),
            max_redemptions: form.max_redemptions === '' ? null : Number(form.max_redemptions),
        })
        codes.value.unshift(created)
        modalOpen.value = false
    } catch (err) {
        const errors = err.response?.data?.errors
        error.value = errors ? Object.values(errors).flat().join(' ') : (err.response?.data?.message ?? 'Something went wrong.')
    } finally {
        saving.value = false
    }
}

async function remove(code) {
    if (!confirm(`Delete discount code "${code.code}"?`)) return

    await store.deleteDiscountCode(code.id)
    codes.value = codes.value.filter((entry) => entry.id !== code.id)
}
</script>

<template>
    <div class="rounded-2xl bg-card p-6 shadow-elevated">
        <div class="flex items-center justify-between">
            <h2 class="text-body text-lg font-bold">Discount Codes</h2>
            <button type="button" class="text-accent flex items-center gap-1 text-sm font-semibold" @click="openCreate">
                <PlusIcon class="h-4 w-4" /> New Code
            </button>
        </div>

        <div v-if="loading" class="flex justify-center py-10">
            <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="codes.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-card-alt text-muted">
                <TicketIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-muted">No discount codes yet.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-border">
            <li v-for="code in codes" :key="code.id" class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div>
                    <p class="text-body font-mono font-semibold">{{ code.code }}</p>
                    <p class="text-sm text-muted">
                        {{ code.discount_type === 'percentage' ? `${code.discount_value}% off` : `${code.discount_value} off` }}
                        <span v-if="code.max_redemptions"> &middot; {{ code.times_redeemed }}/{{ code.max_redemptions }} used</span>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="code.is_valid_now ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ code.is_valid_now ? 'Active' : 'Inactive' }}
                    </span>
                    <button type="button" class="text-muted hover:text-red-600" @click="remove(code)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </li>
        </ul>

        <Modal v-model="modalOpen" title="New Discount Code">
            <form class="space-y-4" novalidate @submit.prevent="save">
                <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>
                <FloatingLabelInput id="discount-code" v-model="form.code" label="Code (e.g. LAUNCH20)" />
                <div class="grid grid-cols-2 gap-4">
                    <SelectInput id="discount-type" v-model="form.discount_type" label="Type" :options="DISCOUNT_TYPE_OPTIONS" />
                    <FloatingLabelInput id="discount-value" v-model="form.discount_value" type="number" label="Value" />
                </div>
                <FloatingLabelInput id="discount-max-redemptions" v-model="form.max_redemptions" type="number" label="Max Redemptions (optional)" />
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-full border border-border px-5 py-2.5 font-semibold text-body" @click="modalOpen = false">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ saving ? 'Creating…' : 'Create Code' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
