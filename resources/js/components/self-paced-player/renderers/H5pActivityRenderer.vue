<script setup>
import { useRoute } from 'vue-router'
import H5pPlayerWidget from '../../lms/H5pPlayerWidget.vue'
import { useSelfPacedPlayerStore } from '../../../stores/selfPacedPlayer'

const props = defineProps({
    activity: { type: Object, required: true },
})

const route = useRoute()
const playerStore = useSelfPacedPlayerStore()

function loadH5pContent() {
    return playerStore.fetchActivityH5pPlayerModel(route.params.courseId, props.activity.id)
}
</script>

<template>
    <H5pPlayerWidget :content-id="String(activity.content?.h5p_content_id)" :load-content="loadH5pContent" />
</template>
