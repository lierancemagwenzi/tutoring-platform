<script setup>
import { computed, ref } from 'vue'
import { ChevronDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: { type: String, default: '' },
    countryCode: { type: String, default: '+27' },
    id: { type: String, required: true },
    label: { type: String, default: 'Phone number' },
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
        <div
            class="bg-card flex w-full items-stretch rounded-xl border transition-colors"
            :class="focused ? 'border-accent border-2' : 'border-border'"
        >
            <div class="relative">
                <button
                    type="button"
                    class="text-body flex h-full items-center gap-1 py-3.5 pr-3 pl-4"
                    @click="codeMenuOpen = !codeMenuOpen"
                >
                    {{ countryCode }}
                    <ChevronDownIcon class="text-muted h-4 w-4" />
                </button>
                <ul
                    v-if="codeMenuOpen"
                    class="bg-card shadow-popover absolute top-full left-0 z-10 mt-1 w-20 rounded-lg border border-border py-1"
                >
                    <li
                        v-for="code in codes"
                        :key="code"
                        class="text-body hover:bg-card-alt cursor-pointer px-4 py-1.5"
                        @click="selectCode(code)"
                    >
                        {{ code }}
                    </li>
                </ul>
            </div>
            <div class="bg-muted/30 my-2.5 w-px shrink-0" />
            <!-- Label is scoped to the number side so it never sits on top of the country code. -->
            <div class="relative min-w-0 flex-1">
                <label
                    :for="id"
                    class="bg-card pointer-events-none absolute left-3 px-1 whitespace-nowrap transition-all duration-150"
                    :class="[
                        floating ? '-top-2.5 text-xs' : 'top-1/2 -translate-y-1/2 text-base',
                        focused ? 'text-accent font-medium' : 'text-muted',
                    ]"
                >
                    {{ label }}
                </label>
                <input
                    :id="id"
                    type="tel"
                    :value="modelValue"
                    autocomplete="tel"
                    class="bg-card text-body w-full rounded-r-xl px-4 py-3.5 outline-none"
                    @input="$emit('update:modelValue', $event.target.value)"
                    @focus="focused = true"
                    @blur="focused = false"
                />
            </div>
        </div>
    </div>
</template>
