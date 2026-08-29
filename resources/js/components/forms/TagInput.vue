<script setup>
import { computed, ref } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    label: { type: String, required: true },
    id: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue'])

const draft = ref('')
const focused = ref(false)
const floating = computed(() => focused.value || props.modelValue.length > 0 || draft.value.length > 0)

function addTag() {
    const value = draft.value.trim()
    if (value && !props.modelValue.includes(value)) {
        emit('update:modelValue', [...props.modelValue, value])
    }
    draft.value = ''
}

function removeTag(index) {
    emit(
        'update:modelValue',
        props.modelValue.filter((_, i) => i !== index),
    )
}

function handleKeydown(event) {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault()
        addTag()
    } else if (event.key === 'Backspace' && !draft.value && props.modelValue.length > 0) {
        removeTag(props.modelValue.length - 1)
    }
}
</script>

<template>
    <div class="relative">
        <label
            :for="id"
            class="pointer-events-none absolute left-3 bg-white px-1 transition-all duration-150"
            :class="[
                floating ? '-top-2.5 text-xs' : 'top-1/2 -translate-y-1/2 text-base',
                focused ? 'text-accent font-medium' : floating ? 'text-gray-600' : 'text-gray-500',
            ]"
        >
            {{ label }}
        </label>
        <div
            class="flex w-full flex-wrap items-center gap-2 rounded-xl border px-4 py-3 transition-colors"
            :class="focused ? 'border-accent border-2' : 'border-gray-300'"
            @click="$el.querySelector('input').focus()"
        >
            <span
                v-for="(tag, index) in modelValue"
                :key="tag"
                class="bg-accent/10 text-accent flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium"
            >
                {{ tag }}
                <button type="button" :aria-label="`Remove ${tag}`" @click.stop="removeTag(index)">
                    <XMarkIcon class="h-3.5 w-3.5" />
                </button>
            </span>
            <input
                :id="id"
                v-model="draft"
                type="text"
                class="min-w-[8rem] flex-1 py-0.5 text-gray-900 outline-none"
                @keydown="handleKeydown"
                @blur="
                    () => {
                        addTag()
                        focused = false
                    }
                "
                @focus="focused = true"
            />
        </div>
    </div>
</template>
