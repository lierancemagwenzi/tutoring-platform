<script setup>
const TABS = [
    { to: '/tutor/settings', label: 'General' },
    { to: '/tutor/settings/connected-accounts', label: 'Connected Accounts' },
    { to: '/tutor/settings/meeting-providers', label: 'Meeting Providers' },
    { to: '/tutor/settings/banking-details', label: 'Banking Details' },
]
</script>

<template>
    <nav class="border-border flex gap-2 border-b">
        <router-link
            v-for="tab in TABS"
            :key="tab.to"
            :to="tab.to"
            class="text-muted hover:text-body border-b-2 border-transparent px-4 py-3 text-sm font-semibold"
            active-class="border-amber text-body"
        >
            {{ tab.label }}
        </router-link>
    </nav>
</template>
