<script setup>
import { CheckIcon } from '@heroicons/vue/24/outline'
</script>

<template>
    <div class="bg-surface flex min-h-screen items-center justify-center px-6">
        <div class="bg-card shadow-popover border-border w-full max-w-md rounded-2xl border p-12 text-center">
            <h1 class="text-body text-2xl font-bold">Application Submitted</h1>
            <div class="bg-amber/10 mx-auto mt-6 flex h-16 w-16 items-center justify-center rounded-full">
                <span class="bg-amber flex h-12 w-12 items-center justify-center rounded-full">
                    <CheckIcon class="text-body h-6 w-6" />
                </span>
            </div>
            <p class="mt-6 text-muted">
                Your tutor application has been submitted successfully.
            </p>
            <p class="mt-3 text-muted">
                Our team will review your information. You will gain access to tutoring features once your
                application has been approved.
            </p>
            <router-link
                to="/tutor"
                class="bg-amber mt-8 inline-block w-full rounded-full py-3.5 font-semibold text-white shadow-elevated transition hover:brightness-95"
            >
                Go to Dashboard
            </router-link>
        </div>
    </div>
</template>
