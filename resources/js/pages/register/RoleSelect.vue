<script setup>
import { useRouter } from 'vue-router'
import { AcademicCapIcon, BookOpenIcon } from '@heroicons/vue/24/outline'
import { useRegistrationStore } from '../../stores/registration'

const registration = useRegistrationStore()
const router = useRouter()

function selectRole(role) {
    registration.role = role
}

function continueRegistration() {
    if (registration.role === 'learner') {
        router.push('/register/age')
    } else {
        router.push('/register/details')
    }
}
</script>

<template>
    <div class="w-full max-w-md">
        <div class="text-center">
            <h1 class="text-ink text-3xl font-bold">Who are you signing up as?</h1>
            <p class="mt-2 text-gray-500">Pick your path to get started</p>
        </div>

        <div class="mt-10 grid grid-cols-2 gap-4">
            <button
                type="button"
                class="rounded-2xl border p-6 text-center transition-colors"
                :class="registration.role === 'guide' ? 'border-accent border-2' : 'border-gray-200'"
                @click="selectRole('guide')"
            >
                <span
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full"
                    :class="registration.role === 'guide' ? 'bg-accent text-white' : 'bg-gray-100 text-gray-700'"
                >
                    <BookOpenIcon class="h-6 w-6" />
                </span>
                <p class="text-ink mt-4 font-bold">A guide</p>
            </button>

            <button
                type="button"
                class="rounded-2xl border p-6 text-center transition-colors"
                :class="registration.role === 'learner' ? 'border-accent border-2' : 'border-gray-200'"
                @click="selectRole('learner')"
            >
                <span
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full"
                    :class="registration.role === 'learner' ? 'bg-accent text-white' : 'bg-gray-100 text-gray-700'"
                >
                    <AcademicCapIcon class="h-6 w-6" />
                </span>
                <p class="text-ink mt-4 font-bold">A learner</p>
            </button>
        </div>

        <button
            type="button"
            :disabled="!registration.role"
            class="bg-amber mt-8 w-full rounded-full py-3.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            @click="continueRegistration"
        >
            Continue
        </button>
    </div>
</template>
