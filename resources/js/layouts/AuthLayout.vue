<script setup>
import {
    AcademicCapIcon,
    BeakerIcon,
    BookOpenIcon,
    CalculatorIcon,
    PencilIcon,
    PuzzlePieceIcon,
    RocketLaunchIcon,
    SparklesIcon,
    StarIcon,
} from '@heroicons/vue/24/outline'
import logo from '../assets/logo.png'

const doodles = [
    { icon: RocketLaunchIcon, top: '8%', left: '20%', rotate: '-12deg', size: 'h-10 w-10' },
    { icon: StarIcon, top: '14%', left: '55%', rotate: '8deg', size: 'h-6 w-6' },
    { icon: PencilIcon, top: '24%', left: '8%', rotate: '20deg', size: 'h-8 w-8' },
    { icon: PuzzlePieceIcon, top: '30%', left: '68%', rotate: '-6deg', size: 'h-9 w-9' },
    { icon: CalculatorIcon, top: '38%', left: '35%', rotate: '10deg', size: 'h-8 w-8' },
    { icon: AcademicCapIcon, top: '46%', left: '15%', rotate: '-15deg', size: 'h-10 w-10' },
    { icon: BookOpenIcon, top: '52%', left: '58%', rotate: '6deg', size: 'h-9 w-9' },
    { icon: SparklesIcon, top: '60%', left: '25%', rotate: '-8deg', size: 'h-6 w-6' },
    { icon: BeakerIcon, top: '68%', left: '45%', rotate: '14deg', size: 'h-8 w-8' },
    { icon: StarIcon, top: '76%', left: '10%', rotate: '-10deg', size: 'h-5 w-5' },
    { icon: RocketLaunchIcon, top: '82%', left: '65%', rotate: '18deg', size: 'h-9 w-9' },
    { icon: PencilIcon, top: '90%', left: '30%', rotate: '-20deg', size: 'h-7 w-7' },
]
</script>

<template>
    <div class="flex min-h-screen w-full">
        <div
            class="from-panel-start to-panel-end relative hidden overflow-hidden bg-gradient-to-b md:block md:w-1/2"
        >
            <div
                v-for="(doodle, index) in doodles"
                :key="index"
                class="absolute text-white/10"
                :style="{ top: doodle.top, left: doodle.left, transform: `rotate(${doodle.rotate})` }"
            >
                <component :is="doodle.icon" :class="doodle.size" />
            </div>

            <div class="relative p-8">
                <img :src="logo" alt="ItsLearnable" class="h-10 w-auto" />
            </div>
        </div>

        <div class="bg-surface flex w-full items-center justify-center px-6 py-12 md:w-1/2">
            <router-view />
        </div>
    </div>
</template>
