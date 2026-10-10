<script setup>
import SpeedTestPanel from '../../components/network/SpeedTestPanel.vue'
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">Internet Speed Test</h1>
        <p class="text-muted mt-1">Check that your connection is ready for live video sessions.</p>

        <div class="bg-card shadow-elevated mt-6 max-w-xl rounded-2xl p-6">
            <SpeedTestPanel />
        </div>

        <p class="text-muted mt-4 max-w-xl text-xs">
            The test measures your connection to Cloudflare's network (which this platform runs through) and uses up to about
            50 MB of data on fast connections, much less on slower ones. For the most useful result, run it on the device and
            network you'll teach from.
        </p>
    </div>
</template>
