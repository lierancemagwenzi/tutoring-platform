<script setup>
import { computed, ref } from 'vue'
import { ChevronDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, required: true },
    id: { type: String, required: true },
    options: { type: Array, required: true }, // [{ value, label }]
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
                floating ? '-top-2.5 text-xs' : 'top-1/2 -translate-y-1/2 text-base',
                focused ? 'text-accent font-medium' : floating ? 'text-gray-600' : 'text-gray-500',
            ]"
        >
            {{ label }}
        </label>
        <select
            :id="id"
            :value="modelValue"
            class="w-full appearance-none rounded-xl border bg-white px-4 py-3.5 text-gray-900 outline-none transition-colors"
            :class="focused ? 'border-accent border-2' : 'border-gray-300'"
            @change="$emit('update:modelValue', $event.target.value)"
            @focus="focused = true"
            @blur="focused = false"
        >
            <option value="" disabled hidden />
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <ChevronDownIcon
            class="pointer-events-none absolute top-1/2 right-3.5 h-5 w-5 -translate-y-1/2"
            :class="focused ? 'text-accent' : 'text-gray-500'"
        />
    </div>
</template>
