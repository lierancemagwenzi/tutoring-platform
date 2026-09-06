<script setup>
import { computed } from 'vue'
import {
    AcademicCapIcon,
    BanknotesIcon,
    BookOpenIcon,
    CalendarDaysIcon,
    ChatBubbleLeftRightIcon,
    CheckBadgeIcon,
    ClipboardDocumentCheckIcon,
    ClipboardDocumentListIcon,
    ClockIcon,
    Cog6ToothIcon,
    DocumentCheckIcon,
    DocumentTextIcon,
    HeartIcon,
    LifebuoyIcon,
    PuzzlePieceIcon,
    QuestionMarkCircleIcon,
    ShieldCheckIcon,
    UserGroupIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline'
import SidebarShell from '../components/navigation/SidebarShell.vue'
import { useAuthStore } from '../stores/auth'

const BASE_ADMIN_GROUPS = [
    {
        key: 'content',
        label: 'Content',
        items: [
            { to: '/admin/subjects', label: 'Subjects', icon: BookOpenIcon },
            { to: '/admin/faqs', label: 'FAQs', icon: QuestionMarkCircleIcon },
            { to: '/admin/terms-and-conditions', label: 'Terms & Conditions', icon: DocumentTextIcon },
            { to: '/admin/privacy-policy', label: 'Privacy Policy', icon: ShieldCheckIcon },
        ],
    },
    {
        key: 'users',
        label: 'Users',
        items: [
            { to: '/admin/tutors', label: 'Tutors', icon: AcademicCapIcon },
            { to: '/admin/students', label: 'Students', icon: UsersIcon },
        ],
    },
    {
        key: 'approvals',
        label: 'Approvals',
        items: [
            { to: '/admin/tutor-approvals', label: 'Tutor Approvals', icon: CheckBadgeIcon },
            { to: '/admin/tutor-subject-requests', label: 'Subject Requests', icon: DocumentCheckIcon },
        ],
    },
    {
        key: 'finance',
        label: 'Finance & Support',
        items: [
            { to: '/admin/financial-rules', label: 'Financial Rules', icon: BanknotesIcon },
            { to: '/admin/financial-transactions', label: 'Transactions', icon: ClipboardDocumentListIcon },
            { to: '/admin/bookings', label: 'Bookings', icon: CalendarDaysIcon },
            { to: '/admin/payment-tickets', label: 'Payment Tickets', icon: ChatBubbleLeftRightIcon },
            { to: '/admin/support-tickets', label: 'Support Tickets', icon: LifebuoyIcon },
        ],
    },
    {
        key: 'platform',
        label: 'Platform',
        items: [
            { to: '/admin/quick-setup', label: 'Quick Setup', icon: ClipboardDocumentCheckIcon },
            { to: '/admin/settings', label: 'Settings', icon: Cog6ToothIcon },
            { to: '/admin/integrations', label: 'Integrations', icon: PuzzlePieceIcon },
            { to: '/admin/system-health', label: 'System Health', icon: HeartIcon },
            { to: '/admin/activity-log', label: 'Activity Log', icon: ClockIcon },
        ],
    },
]

const auth = useAuthStore()

const userName = computed(() => {
    const user = auth.user
    return user ? `${user.first_name} ${user.last_name}` : 'Admin'
})

// Inviting/deactivating/deleting other admins is super-admin-only — hide
// the nav link entirely for regular admins. The real enforcement is the
// `super_admin` route middleware; this is UX polish only.
const ADMIN_GROUPS = computed(() => {
    if (!auth.user?.is_super_admin) {
        return BASE_ADMIN_GROUPS
    }

    return BASE_ADMIN_GROUPS.map((group) =>
        group.key === 'platform'
            ? { ...group, items: [...group.items, { to: '/admin/admins', label: 'Admins', icon: UserGroupIcon }] }
            : group,
    )
})
</script>

<template>
    <SidebarShell :user-name="userName" user-role="Administrator" home-route="/admin" :groups="ADMIN_GROUPS">
        <router-view />
    </SidebarShell>
</template>
