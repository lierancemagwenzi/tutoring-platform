<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'

defineProps({
    modelValue: { type: Boolean, required: true },
    title: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue'])

function close() {
    emit('update:modelValue', false)
}
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" @click.self="close">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <h2 class="text-ink text-lg font-bold">{{ title }}</h2>
                    <button type="button" aria-label="Close" class="text-gray-400 hover:text-gray-600" @click="close">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-4">
                    <slot />
                </div>

                <div v-if="$slots.footer" class="mt-6 flex justify-end gap-3">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
