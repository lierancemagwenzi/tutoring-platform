<script setup>
import { computed, reactive, ref, watch } from 'vue'
import Modal from '../../common/Modal.vue'
import FloatingLabelInput from '../../forms/FloatingLabelInput.vue'
import SelectInput from '../../forms/SelectInput.vue'
import FileUploadInput from '../../forms/FileUploadInput.vue'
import { EXTERNAL_MEDIA_TYPES, MEDIA_TYPE_OPTIONS, mediaTypeMeta } from '../../../lms/mediaTypes'

// Upload a new file (or link a YouTube/Vimeo video) for a Content block or
// Content activity, or edit one already there. Where it's stored differs per
// host, so the host passes `save(payload, existingItem)` — which creates or
// updates the row and resolves with it. Emits the saved row; placing it in
// the item list is the host's job.
const props = defineProps({
    modelValue: { type: Boolean, required: true },
    save: { type: Function, required: true },
    item: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const saving = ref(false)
const error = ref('')
const form = reactive({ mediaType: 'pdf', title: '', file: null, externalUrl: '' })
const existingFileName = ref('')

const isExternal = computed(() => EXTERNAL_MEDIA_TYPES.includes(form.mediaType))
const canSave = computed(() => {
    if (!form.title.trim()) return false
    if (isExternal.value) return Boolean(form.externalUrl.trim())
    return Boolean(form.file) || Boolean(props.item && !EXTERNAL_MEDIA_TYPES.includes(props.item.media_type))
})

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return
        error.value = ''
        form.mediaType = props.item?.media_type ?? 'pdf'
        form.title = props.item?.title ?? ''
        form.file = null
        form.externalUrl = props.item && EXTERNAL_MEDIA_TYPES.includes(props.item.media_type) ? props.item.url : ''
        existingFileName.value = props.item?.original_name ?? ''
    },
)

function onFileSelected(file) {
    form.file = file
    existingFileName.value = file.name
    // Save a step: default the title to the file name.
    if (!form.title.trim()) {
        form.title = file.name.replace(/\.[^.]+$/, '')
    }
}

async function submit() {
    saving.value = true
    error.value = ''

    const payload = {
        media_type: form.mediaType,
        title: form.title.trim(),
        status: props.item?.status ?? 'published',
        ...(isExternal.value ? { external_url: form.externalUrl.trim() } : {}),
        ...(form.file ? { file: form.file } : {}),
    }

    try {
        emit('saved', await props.save(payload, props.item))
        emit('update:modelValue', false)
    } catch (err) {
        const errors = err.response?.data?.errors
        error.value = errors ? Object.values(errors).flat().join(' ') : (err.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <Modal :model-value="modelValue" :title="item ? 'Edit File' : 'Add File or Video'" @update:model-value="emit('update:modelValue', $event)">
        <form class="space-y-4 pt-2" novalidate @submit.prevent="submit">
            <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

            <SelectInput id="content-media-type" v-model="form.mediaType" label="Type" :options="MEDIA_TYPE_OPTIONS" />

            <FileUploadInput
                v-if="!isExternal"
                id="content-media-file"
                label="File"
                :hint="mediaTypeMeta(form.mediaType).accept"
                :accept="mediaTypeMeta(form.mediaType).accept"
                :file-name="existingFileName"
                @select="onFileSelected"
            />
            <FloatingLabelInput v-else id="content-media-url" v-model="form.externalUrl" label="Video URL" />

            <FloatingLabelInput id="content-media-title" v-model="form.title" label="Title" />

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="rounded-full border border-border px-5 py-2.5 font-semibold text-body" @click="emit('update:modelValue', false)">
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="saving || !canSave"
                    class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ saving ? 'Uploading…' : item ? 'Save' : 'Add' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
