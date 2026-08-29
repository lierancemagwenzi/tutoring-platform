<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, required: true },
    id: { type: String, required: true },
    type: { type: String, default: 'text' },
    autocomplete: { type: String, default: 'off' },
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
        <input
            :id="id"
            :type="type"
            :value="modelValue"
            :autocomplete="autocomplete"
            class="w-full rounded-xl border px-4 py-3.5 text-gray-900 outline-none transition-colors"
            :class="[focused ? 'border-accent border-2' : 'border-gray-300', $slots.icon ? 'pr-11' : '']"
            @input="$emit('update:modelValue', $event.target.value)"
            @focus="focused = true"
            @blur="focused = false"
        />
        <div v-if="$slots.icon" class="absolute top-1/2 right-3.5 -translate-y-1/2" :class="focused ? 'text-accent' : 'text-gray-700'">
            <slot name="icon" />
        </div>
    </div>
</template>
