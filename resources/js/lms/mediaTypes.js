import { ArchiveBoxIcon, DocumentIcon, MusicalNoteIcon, PhotoIcon, VideoCameraIcon } from '@heroicons/vue/24/outline'

// Every media type a lesson file can be (mirrors App\Enums\MediaType), with
// the file extensions its upload accepts — shared by the Content block
// editor and the legacy Media block manager.
export const MEDIA_TYPE_OPTIONS = [
    { value: 'pdf', label: 'PDF', accept: '.pdf', icon: DocumentIcon },
    { value: 'image', label: 'Image', accept: '.jpg,.jpeg,.png,.webp,.gif', icon: PhotoIcon },
    { value: 'video_upload', label: 'Uploaded Video', accept: '.mp4,.mov,.avi,.webm', icon: VideoCameraIcon },
    { value: 'video_youtube', label: 'YouTube Video', icon: VideoCameraIcon },
    { value: 'video_vimeo', label: 'Vimeo Video', icon: VideoCameraIcon },
    { value: 'zip', label: 'ZIP Archive', accept: '.zip', icon: ArchiveBoxIcon },
    { value: 'doc', label: 'Word Document (.doc)', accept: '.doc', icon: DocumentIcon },
    { value: 'docx', label: 'Word Document (.docx)', accept: '.docx', icon: DocumentIcon },
    { value: 'ppt', label: 'PowerPoint (.ppt)', accept: '.ppt', icon: DocumentIcon },
    { value: 'pptx', label: 'PowerPoint (.pptx)', accept: '.pptx', icon: DocumentIcon },
    { value: 'xls', label: 'Excel (.xls)', accept: '.xls', icon: DocumentIcon },
    { value: 'xlsx', label: 'Excel (.xlsx)', accept: '.xlsx', icon: DocumentIcon },
    { value: 'csv', label: 'CSV', accept: '.csv', icon: DocumentIcon },
    { value: 'txt', label: 'Text File', accept: '.txt', icon: DocumentIcon },
    { value: 'mp3', label: 'Audio (MP3)', accept: '.mp3', icon: MusicalNoteIcon },
    { value: 'wav', label: 'Audio (WAV)', accept: '.wav', icon: MusicalNoteIcon },
]

// Media types that are a link to a hosted video rather than an uploaded file.
export const EXTERNAL_MEDIA_TYPES = ['video_youtube', 'video_vimeo']

export function mediaTypeMeta(mediaType) {
    return MEDIA_TYPE_OPTIONS.find((option) => option.value === mediaType) ?? MEDIA_TYPE_OPTIONS[0]
}

// How a file is described to learners (vs. the Insider-facing upload labels above).
const LEARNER_LABELS = {
    pdf: 'PDF',
    image: 'Image',
    video_upload: 'Video',
    video_youtube: 'YouTube video',
    video_vimeo: 'Vimeo video',
    zip: 'ZIP archive',
    doc: 'Word document',
    docx: 'Word document',
    ppt: 'PowerPoint presentation',
    pptx: 'PowerPoint presentation',
    xls: 'Excel spreadsheet',
    xlsx: 'Excel spreadsheet',
    csv: 'CSV spreadsheet',
    txt: 'Text file',
    mp3: 'Audio',
    wav: 'Audio',
}

export function learnerMediaLabel(mediaType) {
    return LEARNER_LABELS[mediaType] ?? 'File'
}
