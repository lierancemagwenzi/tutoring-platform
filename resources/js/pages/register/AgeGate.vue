<script setup>
import { useRouter } from 'vue-router'
import { useRegistrationStore } from '../../stores/registration'

const registration = useRegistrationStore()
const router = useRouter()

function selectAge(bracket) {
    registration.ageBracket = bracket
}

function continueRegistration() {
    router.push('/register/details')
}
</script>

<template>
    <div class="w-full max-w-md">
        <div class="text-center">
            <h1 class="text-ink text-3xl font-bold">Before we get started</h1>
            <p class="mt-2 text-gray-500">We just need to know how old you are</p>
        </div>

        <div class="mt-10 grid grid-cols-2 gap-4">
            <button
                type="button"
                class="rounded-2xl border p-6 text-center font-bold transition-colors"
                :class="registration.ageBracket === 'over18' ? 'border-accent text-ink border-2' : 'border-gray-200 text-ink'"
                @click="selectAge('over18')"
            >
                I'm 18 or older
            </button>

            <button
                type="button"
                class="rounded-2xl border p-6 text-center font-bold transition-colors"
                :class="registration.ageBracket === 'under18' ? 'border-accent text-ink border-2' : 'border-gray-200 text-ink'"
                @click="selectAge('under18')"
            >
                I'm under 18
            </button>
        </div>

        <button
            type="button"
            :disabled="!registration.ageBracket"
            class="bg-amber mt-8 w-full rounded-full py-3.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            @click="continueRegistration"
        >
            Continue
        </button>
    </div>
</template>
