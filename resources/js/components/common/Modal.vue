<script setup>
import { computed } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    title: { type: String, required: true },
    // 'md' (default) fits every existing confirmation/form dialog — 'xl' is
    // for content that needs real room to breathe, like the H5P preview
    // player, without resizing every other modal in the app.
    size: { type: String, default: 'md', validator: (value) => ['md', 'xl'].includes(value) },
})

const emit = defineEmits(['update:modelValue'])

const maxWidthClass = computed(() => (props.size === 'xl' ? 'max-w-5xl' : 'max-w-md'))

function close() {
    emit('update:modelValue', false)
}
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" @click.self="close">
            <div class="bg-card shadow-popover flex max-h-[90vh] w-full flex-col rounded-2xl p-6" :class="maxWidthClass">
                <div class="flex shrink-0 items-center justify-between">
                    <h2 class="text-body text-lg font-bold">{{ title }}</h2>
                    <button type="button" aria-label="Close" class="text-muted hover:text-body" @click="close">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-4 overflow-y-auto">
                    <slot />
                </div>

                <div v-if="$slots.footer" class="mt-6 flex shrink-0 justify-end gap-3">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
