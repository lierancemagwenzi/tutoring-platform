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
            class="bg-card pointer-events-none absolute left-3 px-1 transition-all duration-150"
            :class="[
                floating ? '-top-2.5 text-xs' : 'top-4 text-base',
                focused ? 'text-accent font-medium' : 'text-muted',
            ]"
        >
            {{ label }}
        </label>
        <textarea
            :id="id"
            :rows="rows"
            :value="modelValue"
            class="bg-card text-body w-full resize-y rounded-xl border px-4 py-3.5 outline-none transition-colors"
            :class="focused ? 'border-accent border-2' : 'border-border'"
            @input="$emit('update:modelValue', $event.target.value)"
            @focus="focused = true"
            @blur="focused = false"
        />
    </div>
</template>
