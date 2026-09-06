<script setup>
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    label: { type: String, required: true },
    id: { type: String, required: true },
    options: { type: Array, required: true }, // [{ value, label }]
})

const emit = defineEmits(['update:modelValue'])

function toggle(value) {
    emit(
        'update:modelValue',
        props.modelValue.includes(value) ? props.modelValue.filter((item) => item !== value) : [...props.modelValue, value],
    )
}
</script>

<template>
    <div>
        <p :id="id" class="text-body mb-2 font-semibold">{{ label }}</p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <label
                v-for="option in options"
                :key="option.value"
                class="border-border has-[:checked]:border-accent flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5"
            >
                <input
                    type="checkbox"
                    class="accent-accent border-border h-4 w-4 rounded"
                    :checked="modelValue.includes(option.value)"
                    @change="toggle(option.value)"
                />
                <span class="text-body text-sm">{{ option.label }}</span>
            </label>
        </div>
    </div>
</template>
