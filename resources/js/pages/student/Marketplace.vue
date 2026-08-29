<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { BuildingStorefrontIcon, ChevronLeftIcon, ChevronRightIcon, FunnelIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import { useMarketplaceStore } from '../../stores/marketplace'
import TutorCard from '../../components/marketplace/TutorCard.vue'
import FilterPanel from '../../components/marketplace/FilterPanel.vue'
import MarketplaceTabs from '../../components/marketplace/MarketplaceTabs.vue'
import CourseSearch from '../../components/marketplace/CourseSearch.vue'
import CourseFilters from '../../components/marketplace/CourseFilters.vue'
import CourseGrid from '../../components/marketplace/CourseGrid.vue'
import Modal from '../../components/common/Modal.vue'
import SelectInput from '../../components/forms/SelectInput.vue'
import FloatingLabelInput from '../../components/forms/FloatingLabelInput.vue'

const SORT_OPTIONS = [
    { value: 'newest', label: 'Newest Tutors' },
    { value: 'lowest_price', label: 'Lowest Price' },
    { value: 'highest_price', label: 'Highest Price' },
    { value: 'most_experienced', label: 'Most Experienced' },
    { value: 'alphabetical', label: 'Alphabetical' },
]

const store = useMarketplaceStore()

const activeTab = ref('services')

// --- Tutoring Services tab (unchanged functionality) ---

const loading = ref(true)
const errorMessage = ref('')
const showMobileFilters = ref(false)

const filters = reactive({
    search: '',
    subjectId: '',
    gradeId: '',
    curriculumId: '',
    categoryId: '',
    sessionFormatId: '',
    priceMin: '',
    priceMax: '',
    language: '',
    yearsExperienceMin: '',
    sort: '',
})

const subjectOptions = computed(() => store.subjects.map((subject) => ({ value: String(subject.id), label: subject.name })))
const gradeOptions = computed(() => store.grades.map((grade) => ({ value: String(grade.id), label: grade.name })))
const curriculumOptions = computed(() => store.curricula.map((curriculum) => ({ value: String(curriculum.id), label: curriculum.name })))
const categoryOptions = computed(() => store.categories.map((category) => ({ value: String(category.id), label: category.name })))
const sessionFormatOptions = computed(() => store.sessionFormats.map((format) => ({ value: String(format.id), label: format.name })))

function buildParams(page) {
    return {
        search: filters.search,
        subject_id: filters.subjectId,
        grade_id: filters.gradeId,
        curriculum_id: filters.curriculumId,
        service_category_id: filters.categoryId,
        session_format_id: filters.sessionFormatId,
        price_min: filters.priceMin,
        price_max: filters.priceMax,
        language: filters.language,
        years_experience_min: filters.yearsExperienceMin,
        sort: filters.sort,
        page,
    }
}

async function applyFilters(page = 1) {
    loading.value = true
    errorMessage.value = ''
    showMobileFilters.value = false

    try {
        await store.searchTutors(buildParams(page))
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        loading.value = false
    }
}

function clearFilters() {
    filters.search = ''
    filters.subjectId = ''
    filters.gradeId = ''
    filters.curriculumId = ''
    filters.categoryId = ''
    filters.sessionFormatId = ''
    filters.priceMin = ''
    filters.priceMax = ''
    filters.language = ''
    filters.yearsExperienceMin = ''
    filters.sort = ''
    applyFilters(1)
}

function goToPage(page) {
    if (page < 1 || page > store.meta.last_page) {
        return
    }
    applyFilters(page)
}

// --- Self-Paced Courses tab ---

const coursesLoading = ref(true)
const coursesError = ref('')
const coursesLoaded = ref(false)
const showMobileCourseFilters = ref(false)

const courseFilters = reactive({
    search: '',
    sort: '',
    subjectId: '',
    gradeId: '',
    difficulty: '',
    language: '',
    price: '',
    tutorId: '',
    durationMax: '',
})

const courseSubjectOptions = computed(() =>
    store.selfPacedCourseFilters.subjects.map((subject) => ({ value: String(subject.id), label: subject.name })),
)
const courseGradeOptions = computed(() =>
    store.selfPacedCourseFilters.grades.map((grade) => ({ value: String(grade.id), label: grade.name })),
)
const courseLanguageOptions = computed(() => store.selfPacedCourseFilters.languages.map((language) => ({ value: language, label: language })))
const courseTutorOptions = computed(() =>
    store.selfPacedCourseFilters.tutors.map((tutor) => ({ value: String(tutor.id), label: tutor.display_name })),
)

function buildCourseParams(page) {
    return {
        search: courseFilters.search,
        sort: courseFilters.sort,
        subject_id: courseFilters.subjectId,
        grade_id: courseFilters.gradeId,
        difficulty: courseFilters.difficulty,
        language: courseFilters.language,
        price: courseFilters.price,
        tutor_id: courseFilters.tutorId,
        duration_max: courseFilters.durationMax,
        page,
    }
}

async function applyCourseFilters(page = 1) {
    coursesLoading.value = true
    coursesError.value = ''
    showMobileCourseFilters.value = false

    try {
        await store.searchSelfPacedCourses(buildCourseParams(page))
        coursesLoaded.value = true
    } catch (error) {
        coursesError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        coursesLoading.value = false
    }
}

function clearCourseFilters() {
    courseFilters.search = ''
    courseFilters.sort = ''
    courseFilters.subjectId = ''
    courseFilters.gradeId = ''
    courseFilters.difficulty = ''
    courseFilters.language = ''
    courseFilters.price = ''
    courseFilters.tutorId = ''
    courseFilters.durationMax = ''
    applyCourseFilters(1)
}

async function selectTab(tab) {
    activeTab.value = tab

    if (tab === 'courses' && !coursesLoaded.value) {
        await store.fetchSelfPacedCourseFilters()
        await applyCourseFilters(1)
    }
}

onMounted(async () => {
    await store.fetchFilterLookups()
    await applyFilters(1)
})
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Marketplace</h1>
        <p class="mt-1 text-gray-500">Browse guides and tutoring services, or explore self-paced courses.</p>

        <MarketplaceTabs v-model="activeTab" class="mt-6" @update:model-value="selectTab" />

        <!-- Tutoring Services -->
        <div v-if="activeTab === 'services'" class="mt-6">
            <div class="flex flex-col gap-3 sm:flex-row">
                <FloatingLabelInput
                    id="search"
                    v-model="filters.search"
                    label="Search by name, subject, or service"
                    class="flex-1"
                    @keyup.enter="applyFilters(1)"
                />
                <SelectInput id="sort" v-model="filters.sort" label="Sort by" :options="SORT_OPTIONS" class="sm:w-56" />
                <button
                    type="button"
                    class="bg-amber flex items-center justify-center gap-2 rounded-full px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-95"
                    @click="applyFilters(1)"
                >
                    <MagnifyingGlassIcon class="h-5 w-5" />
                    Search
                </button>
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-full border border-gray-300 px-6 py-3 font-semibold text-gray-700 lg:hidden"
                    @click="showMobileFilters = true"
                >
                    <FunnelIcon class="h-5 w-5" />
                    Filters
                </button>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-4">
                <div class="hidden rounded-2xl bg-white p-6 shadow-sm lg:col-span-1 lg:block">
                    <h2 class="text-ink mb-4 font-bold">Filters</h2>
                    <FilterPanel
                        :filters="filters"
                        :subject-options="subjectOptions"
                        :grade-options="gradeOptions"
                        :curriculum-options="curriculumOptions"
                        :category-options="categoryOptions"
                        :session-format-options="sessionFormatOptions"
                        @apply="applyFilters(1)"
                        @clear="clearFilters"
                    />
                </div>

                <div class="lg:col-span-3">
                    <p v-if="errorMessage" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

                    <div v-if="loading" class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-for="n in 6" :key="n" class="animate-pulse rounded-2xl bg-white p-5 shadow-sm">
                            <div class="flex items-center gap-4">
                                <div class="h-16 w-16 rounded-full bg-gray-200" />
                                <div class="flex-1 space-y-2">
                                    <div class="h-4 w-2/3 rounded bg-gray-200" />
                                    <div class="h-3 w-1/3 rounded bg-gray-200" />
                                </div>
                            </div>
                            <div class="mt-4 space-y-2">
                                <div class="h-3 w-full rounded bg-gray-200" />
                                <div class="h-3 w-5/6 rounded bg-gray-200" />
                            </div>
                        </div>
                    </div>

                    <div v-else-if="store.tutors.length === 0" class="flex flex-col items-center rounded-2xl bg-white py-24 text-center shadow-sm">
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                            <BuildingStorefrontIcon class="h-8 w-8" />
                        </span>
                        <p class="mt-4 text-gray-500">No tutors match your search.</p>
                        <button
                            type="button"
                            class="bg-amber mt-6 rounded-full px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-95"
                            @click="clearFilters"
                        >
                            Clear Filters
                        </button>
                    </div>

                    <template v-else>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                            <TutorCard v-for="tutor in store.tutors" :key="tutor.id" :tutor="tutor" />
                        </div>

                        <div class="mt-8 flex items-center justify-center gap-4">
                            <button
                                type="button"
                                :disabled="store.meta.current_page <= 1"
                                class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 text-gray-600 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="goToPage(store.meta.current_page - 1)"
                            >
                                <ChevronLeftIcon class="h-5 w-5" />
                            </button>
                            <span class="text-sm text-gray-500">Page {{ store.meta.current_page }} of {{ store.meta.last_page }}</span>
                            <button
                                type="button"
                                :disabled="store.meta.current_page >= store.meta.last_page"
                                class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 text-gray-600 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="goToPage(store.meta.current_page + 1)"
                            >
                                <ChevronRightIcon class="h-5 w-5" />
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <Modal v-model="showMobileFilters" title="Filters">
                <FilterPanel
                    :filters="filters"
                    :subject-options="subjectOptions"
                    :grade-options="gradeOptions"
                    :curriculum-options="curriculumOptions"
                    :category-options="categoryOptions"
                    :session-format-options="sessionFormatOptions"
                    @apply="applyFilters(1)"
                    @clear="clearFilters"
                />
            </Modal>
        </div>

        <!-- Self-Paced Courses -->
        <div v-else class="mt-6">
            <div class="flex flex-col gap-3 sm:flex-row">
                <CourseSearch
                    v-model:search="courseFilters.search"
                    v-model:sort="courseFilters.sort"
                    class="flex-1"
                    @search="applyCourseFilters(1)"
                />
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-full border border-gray-300 px-6 py-3 font-semibold text-gray-700 lg:hidden"
                    @click="showMobileCourseFilters = true"
                >
                    <FunnelIcon class="h-5 w-5" />
                    Filters
                </button>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-4">
                <div class="hidden rounded-2xl bg-white p-6 shadow-sm lg:col-span-1 lg:block">
                    <h2 class="text-ink mb-4 font-bold">Filters</h2>
                    <CourseFilters
                        :filters="courseFilters"
                        :subject-options="courseSubjectOptions"
                        :grade-options="courseGradeOptions"
                        :language-options="courseLanguageOptions"
                        :tutor-options="courseTutorOptions"
                        @apply="applyCourseFilters(1)"
                        @clear="clearCourseFilters"
                    />
                </div>

                <div class="lg:col-span-3">
                    <CourseGrid
                        :courses="store.selfPacedCourses"
                        :meta="store.selfPacedCoursesMeta"
                        :loading="coursesLoading"
                        :error-message="coursesError"
                        @page="applyCourseFilters"
                    />
                </div>
            </div>

            <Modal v-model="showMobileCourseFilters" title="Filters">
                <CourseFilters
                    :filters="courseFilters"
                    :subject-options="courseSubjectOptions"
                    :grade-options="courseGradeOptions"
                    :language-options="courseLanguageOptions"
                    :tutor-options="courseTutorOptions"
                    @apply="applyCourseFilters(1)"
                    @clear="clearCourseFilters"
                />
            </Modal>
        </div>
    </div>
</template>
