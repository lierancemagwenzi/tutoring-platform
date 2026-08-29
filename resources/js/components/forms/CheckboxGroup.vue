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
        <p :id="id" class="mb-2 font-semibold text-gray-700">{{ label }}</p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <label
                v-for="option in options"
                :key="option.value"
                class="flex cursor-pointer items-center gap-2 rounded-xl border border-gray-300 px-3 py-2.5 has-[:checked]:border-accent"
            >
                <input
                    type="checkbox"
                    class="accent-accent h-4 w-4 rounded border-gray-300"
                    :checked="modelValue.includes(option.value)"
                    @change="toggle(option.value)"
                />
                <span class="text-sm text-gray-900">{{ option.label }}</span>
            </label>
        </div>
    </div>
</template>
