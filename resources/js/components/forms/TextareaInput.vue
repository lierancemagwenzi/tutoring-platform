<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, required: true },
    id: { type: String, required: true },
    rows: { type: Number, default: 4 },
})

defineEmits(['update:modelValue'])

const focused = ref(false)
const floating = computed(() => focused.value || props.modelValue.length > 0)
</script>

<template>
    <div class="relative">
        <label
            :for="id"
            class="pointer-events-none absolute left-3 bg-white px-1 transition-all duration-150"
            :class="[
                floating ? '-top-2.5 text-xs' : 'top-4 text-base',
                focused ? 'text-accent font-medium' : floating ? 'text-gray-600' : 'text-gray-500',
            ]"
        >
            {{ label }}
        </label>
        <textarea
            :id="id"
            :rows="rows"
            :value="modelValue"
            class="w-full resize-y rounded-xl border px-4 py-3.5 text-gray-900 outline-none transition-colors"
            :class="focused ? 'border-accent border-2' : 'border-gray-300'"
            @input="$emit('update:modelValue', $event.target.value)"
            @focus="focused = true"
            @blur="focused = false"
        />
    </div>
</template>
