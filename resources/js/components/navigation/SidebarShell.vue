<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
    ArrowRightStartOnRectangleIcon,
    Bars3Icon,
    BellIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    HomeIcon,
    MoonIcon,
    SunIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '../../stores/auth'
import { useThemeStore } from '../../stores/theme'
import { useNotificationsStore } from '../../stores/notifications'

const props = defineProps({
    userName: { type: String, required: true },
    userRole: { type: String, default: 'Administrator' },
    homeRoute: { type: String, default: '/' },
    // [{ key, label, items: [{ to, label, icon }] }]
    groups: { type: Array, default: () => [] },
    // Pinned items rendered after groups, always visible, not collapsible (e.g. Settings, Contact us)
    trailingLinks: { type: Array, default: () => [] },
})

const auth = useAuthStore()
const theme = useThemeStore()
const notifications = useNotificationsStore()
const router = useRouter()
const menuOpen = ref(false)
const notificationsOpen = ref(false)
const collapsed = ref(false)

const openGroups = reactive(Object.fromEntries(props.groups.map((group) => [group.key, true])))

function toggleGroup(key) {
    openGroups[key] = !openGroups[key]
}

async function handleLogout() {
    menuOpen.value = false
    await auth.logout()
    router.push('/login')
}

function timeAgo(isoString) {
    const seconds = Math.floor((Date.now() - new Date(isoString).getTime()) / 1000)
    if (seconds < 60) return 'just now'
    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    return `${days}d ago`
}

async function handleNotificationClick(item) {
    notificationsOpen.value = false
    await notifications.markRead(item.id)
    if (item.url) {
        router.push(item.url)
    }
}

onMounted(() => {
    notifications.fetchList()
})
</script>

<template>
    <div class="flex min-h-screen w-full bg-gray-100">
        <aside
            class="from-panel-start to-panel-end flex shrink-0 flex-col gap-6 bg-gradient-to-b py-6 text-white transition-all duration-200"
            :class="collapsed ? 'w-28 items-center' : 'w-64 px-4'"
        >
            <button
                type="button"
                aria-label="Toggle menu"
                class="text-white/80 hover:text-white"
                :class="collapsed ? 'self-start pl-6' : 'self-start'"
                @click="collapsed = !collapsed"
            >
                <Bars3Icon class="h-6 w-6" />
            </button>

            <nav class="flex flex-1 flex-col gap-1 overflow-y-auto" :class="collapsed ? 'items-center gap-6' : ''">
                <router-link
                    :to="homeRoute"
                    class="flex shrink-0 items-center gap-3 rounded-lg text-xs text-white/80"
                    :class="collapsed ? 'flex-col gap-1' : 'px-3 py-2 text-sm'"
                    active-class="text-amber font-medium"
                >
                    <HomeIcon class="h-6 w-6 shrink-0" />
                    <span>Home</span>
                </router-link>

                <template v-if="collapsed">
                    <router-link
                        v-for="item in groups.flatMap((group) => group.items)"
                        :key="item.to"
                        :to="item.to"
                        class="flex flex-col items-center gap-1 text-xs text-white/80"
                        active-class="text-amber font-medium"
                    >
                        <component :is="item.icon" class="h-6 w-6 shrink-0" />
                    </router-link>
                    <component
                        :is="item.href ? 'a' : 'router-link'"
                        v-for="item in trailingLinks"
                        :key="item.to ?? item.href"
                        :to="item.to"
                        :href="item.href"
                        class="flex flex-col items-center gap-1 text-xs text-white/80"
                        active-class="text-amber font-medium"
                    >
                        <component :is="item.icon" class="h-6 w-6 shrink-0" />
                    </component>
                </template>

                <template v-else>
                    <div v-for="group in groups" :key="group.key" class="mt-2 first:mt-0">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-semibold tracking-wide text-white/60 uppercase hover:text-white/90"
                            @click="toggleGroup(group.key)"
                        >
                            <span>{{ group.label }}</span>
                            <span class="flex items-center gap-1.5">
                                <span v-if="!openGroups[group.key]" class="text-[10px] text-white/40">{{ group.items.length }}</span>
                                <ChevronDownIcon v-if="openGroups[group.key]" class="h-3.5 w-3.5" />
                                <ChevronRightIcon v-else class="h-3.5 w-3.5" />
                            </span>
                        </button>
                        <div v-if="openGroups[group.key]" class="mt-1 flex flex-col gap-0.5">
                            <router-link
                                v-for="item in group.items"
                                :key="item.to"
                                :to="item.to"
                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-white/80 hover:bg-white/5"
                                active-class="bg-white/10 text-amber font-medium"
                            >
                                <component :is="item.icon" class="h-5 w-5 shrink-0" />
                                <span class="truncate">{{ item.label }}</span>
                            </router-link>
                        </div>
                    </div>

                    <div class="mt-auto flex flex-col gap-0.5 border-t border-white/10 pt-3">
                        <component
                            :is="item.href ? 'a' : 'router-link'"
                            v-for="item in trailingLinks"
                            :key="item.to ?? item.href"
                            :to="item.to"
                            :href="item.href"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-white/80 hover:bg-white/5"
                            active-class="bg-white/10 text-amber font-medium"
                        >
                            <component :is="item.icon" class="h-5 w-5 shrink-0" />
                            <span class="truncate">{{ item.label }}</span>
                        </component>
                    </div>
                </template>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="bg-ink flex items-center justify-end gap-6 px-8 py-4">
                <div class="relative">
                    <button
                        type="button"
                        aria-label="Notifications"
                        class="relative text-white"
                        @click="notificationsOpen = !notificationsOpen"
                    >
                        <BellIcon class="h-6 w-6" />
                        <span v-if="notifications.unreadCount > 0" class="bg-amber absolute top-0 right-0 h-2 w-2 rounded-full" />
                    </button>

                    <div
                        v-if="notificationsOpen"
                        class="absolute top-full right-0 mt-2 w-80 overflow-hidden rounded-xl bg-white text-gray-900 shadow-lg"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                            <span class="text-sm font-semibold">Notifications</span>
                            <button
                                v-if="notifications.unreadCount > 0"
                                type="button"
                                class="text-accent text-xs font-semibold"
                                @click="notifications.markAllRead()"
                            >
                                Mark all read
                            </button>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <p v-if="notifications.items.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">
                                No notifications yet.
                            </p>
                            <button
                                v-for="item in notifications.items"
                                :key="item.id"
                                type="button"
                                class="flex w-full flex-col gap-0.5 border-b border-gray-50 px-4 py-3 text-left last:border-0 hover:bg-gray-50"
                                :class="!item.read ? 'bg-amber/5' : ''"
                                @click="handleNotificationClick(item)"
                            >
                                <span class="flex items-center gap-2">
                                    <span v-if="!item.read" class="bg-amber h-1.5 w-1.5 shrink-0 rounded-full" />
                                    <span class="truncate text-sm font-semibold text-gray-900">{{ item.title }}</span>
                                </span>
                                <span class="line-clamp-2 text-xs text-gray-500">{{ item.body }}</span>
                                <span class="text-[11px] text-gray-400">{{ timeAgo(item.created_at) }}</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <button type="button" class="flex items-center gap-2 text-white" @click="menuOpen = !menuOpen">
                        <span class="bg-amber flex h-9 w-9 items-center justify-center rounded-full font-semibold text-white">
                            {{ userName.charAt(0) }}
                        </span>
                        <span class="text-left text-sm leading-tight">
                            <span class="block font-medium">{{ userName }}</span>
                            <span class="block text-white/70">{{ userRole }}</span>
                        </span>
                        <ChevronDownIcon class="h-4 w-4" />
                    </button>

                    <div
                        v-if="menuOpen"
                        class="absolute top-full right-0 mt-2 w-44 overflow-hidden rounded-xl bg-white py-1 text-gray-900 shadow-lg"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm hover:bg-gray-50"
                            @click="theme.toggle()"
                        >
                            <MoonIcon v-if="theme.theme === 'default'" class="h-4 w-4" />
                            <SunIcon v-else class="h-4 w-4" />
                            {{ theme.theme === 'default' ? 'Dark theme' : 'Default theme' }}
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm hover:bg-gray-50"
                            @click="handleLogout"
                        >
                            <ArrowRightStartOnRectangleIcon class="h-4 w-4" />
                            Log out
                        </button>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
