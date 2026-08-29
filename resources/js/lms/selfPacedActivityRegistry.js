import {
    ArrowDownTrayIcon,
    BookOpenIcon,
    ClipboardDocumentListIcon,
    DocumentIcon,
    DocumentTextIcon,
    LinkIcon,
    PhotoIcon,
    PresentationChartBarIcon,
    PuzzlePieceIcon,
    SpeakerWaveIcon,
    TableCellsIcon,
    VideoCameraIcon,
} from '@heroicons/vue/24/outline'
import AudioRenderer from '../components/self-paced-player/renderers/AudioRenderer.vue'
import DocumentRenderer from '../components/self-paced-player/renderers/DocumentRenderer.vue'
import DownloadRenderer from '../components/self-paced-player/renderers/DownloadRenderer.vue'
import ExternalResourceRenderer from '../components/self-paced-player/renderers/ExternalResourceRenderer.vue'
import ImageGalleryRenderer from '../components/self-paced-player/renderers/ImageGalleryRenderer.vue'
import InstructionRenderer from '../components/self-paced-player/renderers/InstructionRenderer.vue'
import KatexActivityRenderer from '../components/self-paced-player/renderers/KatexActivityRenderer.vue'
import MermaidActivityRenderer from '../components/self-paced-player/renderers/MermaidActivityRenderer.vue'
import PdfRenderer from '../components/self-paced-player/renderers/PdfRenderer.vue'
import RichTextRenderer from '../components/self-paced-player/renderers/RichTextRenderer.vue'
import VideoRenderer from '../components/self-paced-player/renderers/VideoRenderer.vue'

// The single source of truth for rendering a self-paced Activity by type.
// Every entry takes the exact same `:activity="activity"` prop, so a future
// activity type is renderable by adding one entry here plus one small
// component — nothing else in the Course Player needs to change.
//
// `autoComplete: true` means the renderer emits an `auto-complete` event
// (currently only video/audio, on native playback end) that the page
// listens for instead of requiring an explicit Mark Complete click.
export const selfPacedActivityRegistry = {
    rich_text: { label: 'Reading', icon: DocumentTextIcon, component: RichTextRenderer, autoComplete: false },
    video: { label: 'Video', icon: VideoCameraIcon, component: VideoRenderer, autoComplete: true },
    audio: { label: 'Audio', icon: SpeakerWaveIcon, component: AudioRenderer, autoComplete: true },
    image_gallery: { label: 'Images', icon: PhotoIcon, component: ImageGalleryRenderer, autoComplete: false },
    pdf: { label: 'PDF', icon: DocumentTextIcon, component: PdfRenderer, autoComplete: false },
    document: { label: 'Document', icon: DocumentIcon, component: DocumentRenderer, autoComplete: false },
    presentation: { label: 'Presentation', icon: PresentationChartBarIcon, component: DocumentRenderer, autoComplete: false },
    spreadsheet: { label: 'Spreadsheet', icon: TableCellsIcon, component: DocumentRenderer, autoComplete: false },
    download: { label: 'Download', icon: ArrowDownTrayIcon, component: DownloadRenderer, autoComplete: false },
    external_resource: { label: 'External Link', icon: LinkIcon, component: ExternalResourceRenderer, autoComplete: false },
    mermaid: { label: 'Diagram', icon: PuzzlePieceIcon, component: MermaidActivityRenderer, autoComplete: false },
    katex: { label: 'Formula', icon: DocumentTextIcon, component: KatexActivityRenderer, autoComplete: false },
    assignment: { label: 'Assignment', icon: ClipboardDocumentListIcon, component: InstructionRenderer, autoComplete: false },
    homework: { label: 'Homework', icon: ClipboardDocumentListIcon, component: InstructionRenderer, autoComplete: false },
    reading: { label: 'Reading', icon: BookOpenIcon, component: InstructionRenderer, autoComplete: false },
}
