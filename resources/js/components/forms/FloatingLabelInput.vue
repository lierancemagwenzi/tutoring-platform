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
            class="bg-card pointer-events-none absolute left-3 px-1 transition-all duration-150"
            :class="[
                floating ? '-top-2.5 text-xs' : 'top-1/2 -translate-y-1/2 text-base',
                focused ? 'text-accent font-medium' : 'text-muted',
            ]"
        >
            {{ label }}
        </label>
        <input
            :id="id"
            :type="type"
            :value="modelValue"
            :autocomplete="autocomplete"
            class="bg-card text-body w-full rounded-xl border px-4 py-3.5 outline-none transition-colors"
            :class="[focused ? 'border-accent border-2' : 'border-border', $slots.icon ? 'pr-11' : '']"
            @input="$emit('update:modelValue', $event.target.value)"
            @focus="focused = true"
            @blur="focused = false"
        />
        <div v-if="$slots.icon" class="absolute top-1/2 right-3.5 -translate-y-1/2" :class="focused ? 'text-accent' : 'text-muted'">
            <slot name="icon" />
        </div>
    </div>
</template>
