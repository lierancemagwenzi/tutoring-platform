<script setup>
import { onBeforeUnmount, watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table'

const props = defineProps({
    modelValue: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'update:json'])

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Underline,
        Link.configure({ openOnClick: false }),
        Image,
        Table.configure({ resizable: true }),
        TableRow,
        TableHeader,
        TableCell,
    ],
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML())
        emit('update:json', editor.getJSON())
    },
})

watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && value !== editor.value.getHTML()) {
            editor.value.commands.setContent(value, { emitUpdate: false })
        }
    },
)

onBeforeUnmount(() => {
    editor.value?.destroy()
})

function addImage() {
    const url = window.prompt('Image URL')
    if (url) {
        editor.value.chain().focus().setImage({ src: url }).run()
    }
}

function addLink() {
    const previousUrl = editor.value.getAttributes('link').href
    const url = window.prompt('Link URL', previousUrl ?? '')

    if (url === null) {
        return
    }

    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
        return
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

function insertTable() {
    editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}
</script>

<template>
    <div v-if="editor" class="rounded-xl border border-border">
        <div class="flex flex-wrap gap-1 border-b border-border p-2">
            <button
                type="button"
                aria-label="Bold"
                class="rounded-lg px-2.5 py-1.5 text-sm font-bold"
                :class="editor.isActive('bold') ? 'bg-accent/10 text-accent' : 'text-muted hover:brightness-95'"
                @click="editor.chain().focus().toggleBold().run()"
            >
                B
            </button>
            <button
                type="button"
                aria-label="Italic"
                class="rounded-lg px-2.5 py-1.5 text-sm italic"
                :class="editor.isActive('italic') ? 'bg-accent/10 text-accent' : 'text-muted hover:brightness-95'"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                I
            </button>
            <button
                type="button"
                aria-label="Underline"
                class="rounded-lg px-2.5 py-1.5 text-sm underline"
                :class="editor.isActive('underline') ? 'bg-accent/10 text-accent' : 'text-muted hover:brightness-95'"
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                U
            </button>
            <span class="mx-1 w-px bg-border" />
            <button
                v-for="level in [1, 2, 3]"
                :key="level"
                type="button"
                class="rounded-lg px-2.5 py-1.5 text-sm font-semibold"
                :class="editor.isActive('heading', { level }) ? 'bg-accent/10 text-accent' : 'text-muted hover:brightness-95'"
                @click="editor.chain().focus().toggleHeading({ level }).run()"
            >
                H{{ level }}
            </button>
            <span class="mx-1 w-px bg-border" />
            <button
                type="button"
                class="rounded-lg px-2.5 py-1.5 text-sm"
                :class="editor.isActive('bulletList') ? 'bg-accent/10 text-accent' : 'text-muted hover:brightness-95'"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                • List
            </button>
            <button
                type="button"
                class="rounded-lg px-2.5 py-1.5 text-sm"
                :class="editor.isActive('orderedList') ? 'bg-accent/10 text-accent' : 'text-muted hover:brightness-95'"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                1. List
            </button>
            <button
                type="button"
                class="rounded-lg px-2.5 py-1.5 text-sm"
                :class="editor.isActive('blockquote') ? 'bg-accent/10 text-accent' : 'text-muted hover:brightness-95'"
                @click="editor.chain().focus().toggleBlockquote().run()"
            >
                Quote
            </button>
            <button
                type="button"
                class="rounded-lg px-2.5 py-1.5 font-mono text-sm"
                :class="editor.isActive('codeBlock') ? 'bg-accent/10 text-accent' : 'text-muted hover:brightness-95'"
                @click="editor.chain().focus().toggleCodeBlock().run()"
            >
                &lt;/&gt;
            </button>
            <span class="mx-1 w-px bg-border" />
            <button type="button" class="rounded-lg px-2.5 py-1.5 text-sm text-muted hover:brightness-95" @click="addLink">Link</button>
            <button type="button" class="rounded-lg px-2.5 py-1.5 text-sm text-muted hover:brightness-95" @click="addImage">Image</button>
            <button type="button" class="rounded-lg px-2.5 py-1.5 text-sm text-muted hover:brightness-95" @click="insertTable">Table</button>
        </div>

        <EditorContent :editor="editor" class="prose prose-sm text-body max-w-none px-4 py-3" />
    </div>
</template>
