import {
    ArrowPathIcon,
    ArrowTopRightOnSquareIcon,
    BeakerIcon,
    BookOpenIcon,
    CalculatorIcon,
    CheckBadgeIcon,
    ClipboardDocumentCheckIcon,
    ClipboardDocumentListIcon,
    DocumentTextIcon,
    LightBulbIcon,
    PhotoIcon,
    PuzzlePieceIcon,
    QuestionMarkCircleIcon,
    RectangleGroupIcon,
    ShareIcon,
    Squares2X2Icon,
} from '@heroicons/vue/24/outline'
import RichTextBlockEditor from '../components/lms/blocks/RichTextBlockEditor.vue'
import MathBlockEditor from '../components/lms/blocks/MathBlockEditor.vue'
import MermaidBlockEditor from '../components/lms/blocks/MermaidBlockEditor.vue'
import { summariseContentItems } from './contentItemTypes'

function stripHtml(html) {
    const text = (html ?? '').replace(/<[^>]*>/g, ' ').trim()
    return text.length > 120 ? `${text.slice(0, 120)}…` : text || 'Empty'
}

/**
 * The plugin registry every content block type is driven by.
 *
 * Adding a future block type is: register one entry here, plus one editor
 * component (mode: 'modal') or one routed page (mode: 'page'). Nothing else
 * in the Lesson Builder needs to change. An optional `group` key clusters
 * entries under a labeled section in the Add Block menu (see the "Learning
 * Activity" types below) — omit it for a top-level, ungrouped entry.
 *
 * `legacy: true` types are no longer offered in the Add Block menu — the
 * Content block replaced them (one block, any number of text/maths/diagram/
 * file items). They stay registered so any remaining rows still display.
 */
export const blockRegistry = {
    content: {
        label: 'Content',
        icon: Squares2X2Icon,
        mode: 'page',
        route: (block) => `/tutor/lesson-blocks/${block.id}/content`,
        summary: (block) => summariseContentItems(block.content?.items),
    },
    rich_text: {
        label: 'Rich Text',
        legacy: true,
        icon: DocumentTextIcon,
        mode: 'modal',
        editor: RichTextBlockEditor,
        summary: (block) => stripHtml(block.content?.html),
    },
    media: {
        label: 'Media',
        legacy: true,
        icon: PhotoIcon,
        mode: 'page',
        route: (block) => `/tutor/lesson-blocks/${block.id}/media`,
        summary: (block) => {
            const count = block.media_items?.length ?? 0
            return count === 1 ? '1 resource' : `${count} resources`
        },
    },
    quiz: {
        label: 'Quiz',
        icon: QuestionMarkCircleIcon,
        mode: 'page',
        route: (block) => `/tutor/quizzes/${block.quiz?.id}/builder`,
        summary: (block) => {
            const count = block.quiz?.questions?.length ?? 0
            return count === 1 ? '1 question' : `${count} questions`
        },
    },
    math: {
        label: 'Mathematics',
        legacy: true,
        icon: CalculatorIcon,
        mode: 'modal',
        editor: MathBlockEditor,
        summary: (block) => block.content?.latex ?? 'Empty',
    },
    mermaid: {
        label: 'Mermaid Diagram',
        legacy: true,
        icon: ShareIcon,
        mode: 'modal',
        editor: MermaidBlockEditor,
        summary: () => 'Diagram',
    },
    h5p: {
        label: 'H5P',
        icon: PuzzlePieceIcon,
        mode: 'page',
        route: (block) => `/tutor/lesson-blocks/${block.id}/h5p`,
        summary: (block) => block.h5p_content?.title ?? 'No content selected',
    },
    assignment: {
        label: 'Assignment',
        icon: ClipboardDocumentCheckIcon,
        mode: 'page',
        group: 'Learning Activity',
        route: (block) => `/tutor/learning-activities/${block.learning_activity?.id}/builder`,
        summary: (block) => block.learning_activity?.title ?? 'Untitled',
    },
    homework: {
        label: 'Homework',
        icon: ClipboardDocumentListIcon,
        mode: 'page',
        group: 'Learning Activity',
        route: (block) => `/tutor/learning-activities/${block.learning_activity?.id}/builder`,
        summary: (block) => block.learning_activity?.title ?? 'Untitled',
    },
    practice: {
        label: 'Practice',
        icon: ArrowPathIcon,
        mode: 'page',
        group: 'Learning Activity',
        route: (block) => `/tutor/learning-activities/${block.learning_activity?.id}/builder`,
        summary: (block) => block.learning_activity?.title ?? 'Untitled',
    },
    assessment: {
        label: 'Assessment',
        icon: CheckBadgeIcon,
        mode: 'page',
        group: 'Learning Activity',
        route: (block) => `/tutor/learning-activities/${block.learning_activity?.id}/builder`,
        summary: (block) => block.learning_activity?.title ?? 'Untitled',
    },
    project: {
        label: 'Project',
        icon: RectangleGroupIcon,
        mode: 'page',
        group: 'Learning Activity',
        route: (block) => `/tutor/learning-activities/${block.learning_activity?.id}/builder`,
        summary: (block) => block.learning_activity?.title ?? 'Untitled',
    },
    lab: {
        label: 'Lab',
        icon: BeakerIcon,
        mode: 'page',
        group: 'Learning Activity',
        route: (block) => `/tutor/learning-activities/${block.learning_activity?.id}/builder`,
        summary: (block) => block.learning_activity?.title ?? 'Untitled',
    },
    reflection: {
        label: 'Reflection',
        icon: LightBulbIcon,
        mode: 'page',
        group: 'Learning Activity',
        route: (block) => `/tutor/learning-activities/${block.learning_activity?.id}/builder`,
        summary: (block) => block.learning_activity?.title ?? 'Untitled',
    },
    reading: {
        label: 'Reading',
        icon: BookOpenIcon,
        mode: 'page',
        group: 'Learning Activity',
        route: (block) => `/tutor/learning-activities/${block.learning_activity?.id}/builder`,
        summary: (block) => block.learning_activity?.title ?? 'Untitled',
    },
    external_activity: {
        label: 'External Activity',
        icon: ArrowTopRightOnSquareIcon,
        mode: 'page',
        group: 'Learning Activity',
        route: (block) => `/tutor/learning-activities/${block.learning_activity?.id}/builder`,
        summary: (block) => block.learning_activity?.title ?? 'Untitled',
    },
}

export function blockTypeOptions() {
    return Object.entries(blockRegistry)
        .filter(([, meta]) => !meta.legacy)
        .map(([type, meta]) => ({ type, ...meta }))
}
