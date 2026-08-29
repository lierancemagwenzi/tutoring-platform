<script setup>
const TABS = [
    { to: '/tutor/settings', label: 'General' },
    { to: '/tutor/settings/connected-accounts', label: 'Connected Accounts' },
    { to: '/tutor/settings/meeting-providers', label: 'Meeting Providers' },
    { to: '/tutor/settings/banking-details', label: 'Banking Details' },
]
</script>

<template>
    <nav class="flex gap-2 border-b border-gray-200">
        <router-link
            v-for="tab in TABS"
            :key="tab.to"
            :to="tab.to"
            class="border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-gray-500 hover:text-gray-700"
            active-class="border-amber text-ink"
        >
            {{ tab.label }}
        </router-link>
    </nav>
</template>
