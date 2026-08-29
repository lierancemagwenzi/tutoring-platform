<script setup>
import { ref, watch } from 'vue'
import { Model } from 'survey-core'
import { SurveyComponent } from 'survey-vue3-ui'
import 'survey-core/survey-core.min.css'

// A genuine reusable SurveyJS wrapper — extracted from the inline
// Model/SurveyComponent construction pattern already used (but never
// factored out) by StudentAttemptPanel.vue. Submits the raw survey-core
// result object unmodified, matching this codebase's `{ raw_result }`
// attempt-completion API shape.
const props = defineProps({
    surveyJson: { type: Object, required: true },
})

const emit = defineEmits(['submit'])

function buildModel(json) {
    const model = new Model(json)
    model.onComplete.add((sender) => emit('submit', sender.data))
    return model
}

const model = ref(buildModel(props.surveyJson))

watch(
    () => props.surveyJson,
    (json) => {
        model.value = buildModel(json)
    },
)
</script>

<template>
    <SurveyComponent :model="model" />
</template>
