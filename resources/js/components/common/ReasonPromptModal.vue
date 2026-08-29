<script setup>
import { ref, watch } from 'vue'
import Modal from './Modal.vue'

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    label: { type: String, default: 'Reason' },
    confirmLabel: { type: String, default: 'Confirm' },
    loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const reason = ref('')

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            reason.value = ''
        }
    },
)

function confirm() {
    if (!reason.value.trim()) {
        return
    }
    emit('confirm', reason.value.trim())
}
</script>

<template>
    <Modal :model-value="modelValue" :title="title" @update:model-value="$emit('update:modelValue', $event)">
        <p v-if="message" class="text-sm text-gray-600">{{ message }}</p>

        <label class="mt-4 block text-sm font-semibold text-gray-700" :for="`reason-${title}`">{{ label }}</label>
        <textarea
            :id="`reason-${title}`"
            v-model="reason"
            rows="3"
            class="focus:border-accent mt-1.5 w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none"
        />

        <template #footer>
            <button
                type="button"
                class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                @click="$emit('update:modelValue', false)"
            >
                Cancel
            </button>
            <button
                type="button"
                :disabled="loading || !reason.trim()"
                class="bg-amber rounded-full px-6 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                @click="confirm"
            >
                {{ loading ? 'Please wait…' : confirmLabel }}
            </button>
        </template>
    </Modal>
</template>
