// Loads H5P's own core/editor client JS/CSS (the classic window.H5PIntegration
// + H5P.init()/H5PEditor.Editor() pattern every non-Node H5P integration
// uses — see App\Services\H5p\H5PService) and wires the legacy jQuery ajax
// calls that JS makes internally to carry this app's Sanctum bearer token,
// since they don't go through our axios instance (see resources/js/services/api.js).

const loadedUrls = new Set()

function loadScript(url) {
    if (loadedUrls.has(url)) return Promise.resolve()
    loadedUrls.add(url)

    return new Promise((resolve, reject) => {
        const script = document.createElement('script')
        script.src = url
        script.onload = resolve
        script.onerror = () => reject(new Error(`Failed to load ${url}`))
        document.head.appendChild(script)
    })
}

function loadStyle(url) {
    if (loadedUrls.has(url)) return
    loadedUrls.add(url)

    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = url
    document.head.appendChild(link)
}

/**
 * Loads a set of H5P core/editor scripts (in order — H5P.js depends on
 * jquery.js loading first) and styles onto the current page, skipping any
 * already loaded. Safe to call repeatedly (e.g. once per player/editor
 * widget mounted).
 */
export async function loadH5pAssets(scripts = [], styles = []) {
    styles.forEach(loadStyle)
    for (const url of scripts) {
        await loadScript(url)
    }
}

/**
 * H5P's own client JS (h5peditor.js, the ajax file uploader, etc.) makes
 * its ajax calls directly via H5P.jQuery — not through our axios instance —
 * so it never picks up the Authorization header api.js's interceptor adds.
 * Configuring jQuery's own ajaxSetup once covers every such call globally.
 *
 * The editor's own <iframe> (see H5pEditorWidget.vue) has a completely
 * separate H5P.jQuery instance from the parent page's, so it needs this
 * called against its own `win` too — pass its contentWindow explicitly.
 */
export function configureH5pAjax(win = window) {
    if (!win.H5P?.jQuery) return

    const token = localStorage.getItem('auth_token')
    if (!token) return

    win.H5P.jQuery.ajaxSetup({
        headers: { Authorization: `Bearer ${token}` },
    })
}

/**
 * The editor's <iframe> (see H5pEditorWidget.vue) makes its first ajax call
 * (fetching the library list) the instant its own jquery.js/h5p.js finish
 * loading — before we get a chance to call configureH5pAjax() against its
 * window, since that requires H5P.jQuery to already exist there. Prepending
 * this as the very first script in the iframe's asset list (ahead of even
 * jquery.js) patches XMLHttpRequest and fetch directly, so the Authorization
 * header is attached from the iframe's first request onward regardless of
 * timing.
 *
 * Both are patched, not just XMLHttpRequest: the H5P Hub content-type
 * gallery (h5p-hub-client.js, a React bundle) makes its own ajax calls via
 * native fetch() rather than H5P.jQuery, so it doesn't pick up
 * configureH5pAjax()'s jQuery.ajaxSetup() headers either. Without patching
 * fetch too, every Hub call (browsing/installing content types) silently
 * goes out unauthenticated, gets a 401, and — since the Hub client has no
 * error boundary and doesn't handle a failed request gracefully — crashes
 * with an uncaught TypeError that blanks the whole gallery.
 */
export function authBootstrapScriptUrl() {
    const token = localStorage.getItem('auth_token')
    if (!token) return null

    const code = `(function () {
        var open = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (method, url) {
            var result = open.apply(this, arguments);
            if (typeof url === 'string' && url.indexOf('/h5p-assets/') !== -1) {
                this.setRequestHeader('Authorization', 'Bearer ${token}');
            }
            return result;
        };

        var fetch = window.fetch;
        window.fetch = function (input, init) {
            var url = typeof input === 'string' ? input : (input && input.url);
            if (typeof url === 'string' && url.indexOf('/h5p-assets/') !== -1) {
                init = init || {};
                var headers = new Headers(init.headers || (input && input.headers) || {});
                headers.set('Authorization', 'Bearer ${token}');
                init = Object.assign({}, init, { headers: headers });
            }
            return fetch.call(this, input, init);
        };
    })();`

    return `data:text/javascript;charset=utf-8,${encodeURIComponent(code)}`
}
