<script setup>
import { computed, ref } from 'vue'
import { ChevronDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: { type: String, default: '' },
    countryCode: { type: String, default: '+27' },
    id: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue', 'update:countryCode'])

const codes = ['+27', '+1', '+44']
const codeMenuOpen = ref(false)
const focused = ref(false)
const floating = computed(() => focused.value || props.modelValue.length > 0)

function selectCode(code) {
    emit('update:countryCode', code)
    codeMenuOpen.value = false
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
            Country code
        </label>
        <div
            class="flex w-full items-stretch rounded-xl border transition-colors"
            :class="focused ? 'border-accent border-2' : 'border-gray-300'"
        >
            <div class="relative">
                <button
                    type="button"
                    class="flex h-full items-center gap-1 py-3.5 pr-3 pl-4 text-gray-900"
                    @click="codeMenuOpen = !codeMenuOpen"
                >
                    {{ countryCode }}
                    <ChevronDownIcon class="h-4 w-4 text-gray-500" />
                </button>
                <ul
                    v-if="codeMenuOpen"
                    class="absolute top-full left-0 z-10 mt-1 w-20 rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                >
                    <li
                        v-for="code in codes"
                        :key="code"
                        class="cursor-pointer px-4 py-1.5 text-gray-900 hover:bg-gray-50"
                        @click="selectCode(code)"
                    >
                        {{ code }}
                    </li>
                </ul>
            </div>
            <div class="w-px shrink-0 bg-gray-300" />
            <input
                :id="id"
                type="tel"
                :value="modelValue"
                autocomplete="tel"
                class="w-full rounded-r-xl px-4 py-3.5 text-gray-900 outline-none"
                @input="$emit('update:modelValue', $event.target.value)"
                @focus="focused = true"
                @blur="focused = false"
            />
        </div>
    </div>
</template>
