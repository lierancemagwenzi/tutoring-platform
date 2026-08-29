<script setup>
import { ref } from 'vue'
import { ArrowPathIcon, DocumentIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    hint: { type: String, default: 'PDF, JPG, JPEG or PNG' },
    accept: { type: String, default: '.pdf,.jpg,.jpeg,.png' },
    fileName: { type: String, default: '' },
    removable: { type: Boolean, default: false },
    uploading: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'remove'])

const input = ref(null)

function triggerPicker() {
    input.value?.click()
}

function handleChange(event) {
    const file = event.target.files?.[0]
    if (file) {
        emit('select', file)
    }
    event.target.value = ''
}
</script>

<template>
    <div>
        <p class="mb-2 font-semibold text-gray-700">{{ label }}</p>

        <div v-if="fileName" class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3.5">
            <div class="flex min-w-0 items-center gap-3">
                <DocumentIcon class="text-accent h-6 w-6 shrink-0" />
                <span class="truncate text-gray-900">{{ fileName }}</span>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <button type="button" class="text-accent flex items-center gap-1 text-sm font-semibold" :disabled="uploading" @click="triggerPicker">
                    <ArrowPathIcon class="h-4 w-4" />
                    Replace
                </button>
                <button
                    v-if="removable"
                    type="button"
                    aria-label="Remove file"
                    class="text-red-600"
                    :disabled="uploading"
                    @click="$emit('remove')"
                >
                    <TrashIcon class="h-4 w-4" />
                </button>
            </div>
        </div>

        <button
            v-else
            type="button"
            class="hover:border-accent flex w-full flex-col items-center gap-1 rounded-xl border-2 border-dashed border-gray-300 px-4 py-8 text-center transition-colors"
            :disabled="uploading"
            @click="triggerPicker"
        >
            <span class="text-accent font-semibold">Choose file</span>
            <span class="text-sm text-gray-500">{{ hint }}</span>
        </button>

        <p v-if="uploading" class="mt-2 text-sm text-gray-500">Uploading…</p>

        <input :id="id" ref="input" type="file" :accept="accept" class="hidden" @change="handleChange" />
    </div>
</template>
