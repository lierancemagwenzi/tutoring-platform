<script setup>
import { computed, onMounted, ref } from 'vue'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'
import { useCoursesStore } from '../../stores/courses'
import SelectInput from '../forms/SelectInput.vue'

const emit = defineEmits(['selected', 'cancelled'])

const store = useCoursesStore()

const loading = ref(true)
const step = ref('course') // 'course' | 'chapter' | 'lesson'

const curriculumId = ref('')
const gradeId = ref('')
const subjectId = ref('')

const selectedCourse = ref(null)
const selectedChapter = ref(null)

const chapters = ref([])
const lessons = ref([])
const chaptersLoading = ref(false)
const lessonsLoading = ref(false)

onMounted(async () => {
    await Promise.all([store.fetchLookups(), store.fetchCourses()])
    loading.value = false
})

const curriculumOptions = computed(() => [
    { value: '', label: 'All Curricula' },
    ...store.curricula.map((curriculum) => ({ value: String(curriculum.id), label: curriculum.name })),
])

const gradeOptions = computed(() => {
    const grades = new Map()
    for (const tutorSubject of store.tutorSubjects) {
        for (const grade of tutorSubject.grades ?? []) {
            grades.set(grade.id, grade.name)
        }
    }
    return [{ value: '', label: 'All Grades' }, ...[...grades.entries()].map(([id, name]) => ({ value: String(id), label: name }))]
})

const subjectOptions = computed(() => [
    { value: '', label: 'All Subjects' },
    ...store.tutorSubjects.map((tutorSubject) => ({ value: String(tutorSubject.subject.id), label: tutorSubject.subject.name })),
])

const filteredCourses = computed(() =>
    store.courses.filter((course) => {
        if (curriculumId.value && String(course.curriculum_id) !== curriculumId.value) return false
        if (gradeId.value && String(course.grade_id) !== gradeId.value) return false
        if (subjectId.value && String(course.subject_id) !== subjectId.value) return false
        return true
    }),
)

async function chooseCourse(course) {
    selectedCourse.value = course
    chaptersLoading.value = true
    step.value = 'chapter'
    chapters.value = await store.fetchChapters(course.id)
    chaptersLoading.value = false
}

async function chooseChapter(chapter) {
    selectedChapter.value = chapter
    lessonsLoading.value = true
    step.value = 'lesson'
    lessons.value = await store.fetchLessons(chapter.id)
    lessonsLoading.value = false
}

function chooseLesson(lesson) {
    emit('selected', lesson)
}

function backToCourses() {
    step.value = 'course'
    selectedCourse.value = null
}

function backToChapters() {
    step.value = 'chapter'
    selectedChapter.value = null
}
</script>

<template>
    <div>
        <nav class="mb-4 flex items-center gap-1 text-sm text-gray-500">
            <button type="button" class="hover:text-accent" :class="step === 'course' && 'text-ink font-semibold'" @click="backToCourses">
                Course
            </button>
            <template v-if="selectedCourse">
                <ChevronRightIcon class="h-3.5 w-3.5" />
                <button type="button" class="hover:text-accent" :class="step === 'chapter' && 'text-ink font-semibold'" @click="backToChapters">
                    {{ selectedCourse.title }}
                </button>
            </template>
            <template v-if="selectedChapter">
                <ChevronRightIcon class="h-3.5 w-3.5" />
                <span class="text-ink font-semibold">{{ selectedChapter.title }}</span>
            </template>
        </nav>

        <div v-if="loading" class="flex justify-center py-12">
            <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else-if="step === 'course'">
            <div class="mb-4 grid grid-cols-3 gap-3">
                <SelectInput id="picker-curriculum" v-model="curriculumId" label="Curriculum" :options="curriculumOptions" />
                <SelectInput id="picker-grade" v-model="gradeId" label="Grade" :options="gradeOptions" />
                <SelectInput id="picker-subject" v-model="subjectId" label="Subject" :options="subjectOptions" />
            </div>

            <p v-if="filteredCourses.length === 0" class="py-8 text-center text-sm text-gray-500">No courses match these filters.</p>
            <ul v-else class="max-h-80 space-y-1 overflow-y-auto">
                <li v-for="course in filteredCourses" :key="course.id">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-xl border border-gray-200 px-4 py-3 text-left hover:border-gray-300 hover:bg-gray-50"
                        @click="chooseCourse(course)"
                    >
                        <span class="text-sm font-medium text-gray-900">{{ course.title }}</span>
                        <ChevronRightIcon class="h-4 w-4 text-gray-400" />
                    </button>
                </li>
            </ul>
        </template>

        <template v-else-if="step === 'chapter'">
            <div v-if="chaptersLoading" class="flex justify-center py-12">
                <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
            </div>
            <template v-else>
                <p v-if="chapters.length === 0" class="py-8 text-center text-sm text-gray-500">This course has no chapters yet.</p>
                <ul v-else class="max-h-80 space-y-1 overflow-y-auto">
                    <li v-for="chapter in chapters" :key="chapter.id">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl border border-gray-200 px-4 py-3 text-left hover:border-gray-300 hover:bg-gray-50"
                            @click="chooseChapter(chapter)"
                        >
                            <span class="text-sm font-medium text-gray-900">{{ chapter.title }}</span>
                            <ChevronRightIcon class="h-4 w-4 text-gray-400" />
                        </button>
                    </li>
                </ul>
            </template>
        </template>

        <template v-else-if="step === 'lesson'">
            <div v-if="lessonsLoading" class="flex justify-center py-12">
                <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
            </div>
            <template v-else>
                <p v-if="lessons.length === 0" class="py-8 text-center text-sm text-gray-500">This chapter has no lessons yet.</p>
                <ul v-else class="max-h-80 space-y-1 overflow-y-auto">
                    <li v-for="lesson in lessons" :key="lesson.id">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl border border-gray-200 px-4 py-3 text-left"
                            :class="lesson.status === 'published' ? 'hover:border-gray-300 hover:bg-gray-50' : 'cursor-not-allowed opacity-50'"
                            :disabled="lesson.status !== 'published'"
                            :title="lesson.status !== 'published' ? 'Publish this lesson before assigning it to a session.' : ''"
                            @click="chooseLesson(lesson)"
                        >
                            <span class="text-sm font-medium text-gray-900">{{ lesson.title }}</span>
                            <span v-if="lesson.status === 'published'" class="text-accent text-xs font-semibold">Assign</span>
                            <span v-else class="text-xs font-semibold text-gray-400 capitalize">{{ lesson.status }}</span>
                        </button>
                    </li>
                </ul>
            </template>
        </template>

        <div class="mt-4 flex justify-end">
            <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700" @click="emit('cancelled')">
                Cancel
            </button>
        </div>
    </div>
</template>
