<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeftIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import logo from '../assets/logo.png'

const route = useRoute()
const router = useRouter()

const step = computed(() => route.meta.step ?? 1)
const totalSteps = computed(() => route.meta.totalSteps ?? 3)
</script>

<template>
    <div class="bg-surface min-h-screen w-full">
        <div class="bg-ink flex items-center justify-between px-6 py-4">
            <button type="button" aria-label="Go back" class="text-white" @click="router.back()">
                <ArrowLeftIcon class="h-6 w-6" />
            </button>
            <div class="flex items-center gap-3">
                <img :src="logo" alt="ItsLearnable" class="h-6 w-auto" />
                <div class="h-1.5 w-32 overflow-hidden rounded-full bg-white/30">
                    <div
                        class="bg-accent h-full rounded-full transition-all duration-300"
                        :style="{ width: `${(step / totalSteps) * 100}%` }"
                    />
                </div>
                <span class="text-sm text-white">Step {{ step }} of {{ totalSteps }}</span>
            </div>
            <router-link to="/login" aria-label="Close" class="text-white">
                <XMarkIcon class="h-6 w-6" />
            </router-link>
        </div>

        <div class="flex justify-center px-6 py-16">
            <router-view />
        </div>
    </div>
</template>
