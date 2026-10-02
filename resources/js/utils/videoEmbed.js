/**
 * Turn a YouTube/Vimeo page URL (what an Insider pastes) into the provider's
 * embeddable player URL — a <video> element can't play a page URL. Returns
 * null for anything unrecognised, so callers can fall back to a plain link.
 *
 * @param {string} mediaType  'video_youtube' | 'video_vimeo'
 * @param {string} rawUrl
 * @returns {string|null}
 */
export function videoEmbedUrl(mediaType, rawUrl) {
    let url
    try {
        url = new URL(rawUrl)
    } catch {
        return null
    }
    const host = url.hostname.replace(/^(www\.|m\.)/, '')

    if (mediaType === 'video_youtube') {
        let id = null
        if (host === 'youtu.be') id = url.pathname.slice(1)
        else if (url.searchParams.get('v')) id = url.searchParams.get('v')
        else id = url.pathname.match(/^\/(?:embed|shorts|live)\/([^/?]+)/)?.[1] ?? null

        return id ? `https://www.youtube-nocookie.com/embed/${id.split('/')[0]}` : null
    }

    if (mediaType === 'video_vimeo') {
        // vimeo.com/123, vimeo.com/123/<privacy hash>, player.vimeo.com/video/123
        const [, id, hash] = url.pathname.match(/(?:\/video)?\/(\d+)(?:\/([0-9a-f]+))?/) ?? []
        if (!id) return null

        return `https://player.vimeo.com/video/${id}${hash ? `?h=${hash}` : ''}`
    }

    return null
}

export const EMBEDDED_VIDEO_TYPES = ['video_youtube', 'video_vimeo']
