<script setup>
import { DocumentDuplicateIcon, EyeIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'

defineProps({
    item: { type: Object, required: true },
    exportUrl: { type: String, default: null },
    selectable: { type: Boolean, default: false },
})

defineEmits(['select', 'preview', 'edit', 'duplicate', 'delete'])
</script>

<template>
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <p class="text-ink truncate font-bold">{{ item.title }}</p>
        <p class="mt-0.5 text-sm text-gray-500">{{ item.main_library }}</p>
        <p v-if="item.curriculum" class="mt-2 inline-block rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
            {{ item.curriculum.name }}
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            <button v-if="selectable" type="button" class="text-accent text-sm font-semibold" @click="$emit('select', item)">Select</button>
            <button type="button" class="text-gray-500 hover:text-gray-700" title="Preview" @click="$emit('preview', item)">
                <EyeIcon class="h-4 w-4" />
            </button>
            <button type="button" class="text-gray-500 hover:text-gray-700" title="Edit" @click="$emit('edit', item)">
                <PencilSquareIcon class="h-4 w-4" />
            </button>
            <button type="button" class="text-gray-500 hover:text-gray-700" title="Duplicate" @click="$emit('duplicate', item)">
                <DocumentDuplicateIcon class="h-4 w-4" />
            </button>
            <button type="button" class="text-red-500 hover:text-red-700" title="Delete" @click="$emit('delete', item)">
                <TrashIcon class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
