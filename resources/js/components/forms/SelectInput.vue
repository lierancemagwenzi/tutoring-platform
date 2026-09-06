<script setup>
import { computed, ref } from 'vue'
import { ChevronDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, required: true },
    id: { type: String, required: true },
    options: { type: Array, required: true }, // [{ value, label }]
    disabled: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])

const focused = ref(false)
const floating = computed(() => focused.value || props.modelValue.length > 0)
</script>

<template>
    <div class="relative">
        <label
            :for="id"
            class="bg-card pointer-events-none absolute left-3 px-1 transition-all duration-150"
            :class="[
                floating ? '-top-2.5 text-xs' : 'top-1/2 -translate-y-1/2 text-base',
                focused ? 'text-accent font-medium' : 'text-muted',
            ]"
        >
            {{ label }}
        </label>
        <select
            :id="id"
            :value="modelValue"
            :disabled="disabled"
            class="bg-card text-body disabled:bg-card-alt disabled:text-muted w-full appearance-none rounded-xl border px-4 py-3.5 outline-none transition-colors disabled:cursor-not-allowed"
            :class="focused ? 'border-accent border-2' : 'border-border'"
            @change="$emit('update:modelValue', $event.target.value)"
            @focus="focused = true"
            @blur="focused = false"
        >
            <option value="" disabled hidden />
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <ChevronDownIcon
            class="pointer-events-none absolute top-1/2 right-3.5 h-5 w-5 -translate-y-1/2"
            :class="focused ? 'text-accent' : 'text-muted'"
        />
    </div>
</template>
