<script setup>
import { computed } from 'vue'
import {
    AcademicCapIcon,
    BanknotesIcon,
    BuildingStorefrontIcon,
    ClipboardDocumentListIcon,
    LifebuoyIcon,
    PhoneIcon,
    QuestionMarkCircleIcon,
    ShoppingBagIcon,
} from '@heroicons/vue/24/outline'
import SidebarShell from '../components/navigation/SidebarShell.vue'
import { useAuthStore } from '../stores/auth'

const STUDENT_GROUPS = [
    {
        key: 'learning',
        label: 'Learning',
        items: [
            { to: '/student/marketplace', label: 'Marketplace', icon: BuildingStorefrontIcon },
            { to: '/student/bookings', label: 'My Bookings', icon: ClipboardDocumentListIcon },
            { to: '/student/my-courses', label: 'My Courses', icon: AcademicCapIcon },
        ],
    },
    {
        key: 'billing',
        label: 'Billing',
        items: [
            { to: '/student/orders', label: 'Purchases', icon: ShoppingBagIcon },
            { to: '/student/financial-transactions', label: 'Payments', icon: BanknotesIcon },
        ],
    },
    {
        key: 'support',
        label: 'Support',
        items: [
            { to: '/student/faqs', label: 'FAQs', icon: QuestionMarkCircleIcon },
            { to: '/student/help-tickets', label: 'Help', icon: LifebuoyIcon },
        ],
    },
]

const STUDENT_TRAILING_LINKS = [{ href: '#', label: 'Contact us', icon: PhoneIcon }]

const auth = useAuthStore()

const userName = computed(() => {
    const user = auth.user
    return user ? `${user.first_name} ${user.last_name}` : 'Student'
})
</script>

<template>
    <SidebarShell
        :user-name="userName"
        user-role="Student"
        home-route="/student"
        :groups="STUDENT_GROUPS"
        :trailing-links="STUDENT_TRAILING_LINKS"
    >
        <router-view />
    </SidebarShell>
</template>
