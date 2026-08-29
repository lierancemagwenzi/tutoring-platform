import 'dotenv/config';
import express from 'express';
import cors from 'cors';
import fileUpload from 'express-fileupload';
import { h5pAjaxExpressRouter } from '@lumieducation/h5p-express';
import { env, createConfig } from './config';
import { createH5P } from './h5p';
import { attachServiceUser, requireApiKey } from './auth';
import { createHealthRouter } from './routes/health';
import { createContentRouter } from './routes/content';
import { createLibrariesRouter } from './routes/libraries';

async function main(): Promise<void> {
    const config = await createConfig();
    const { h5pEditor, h5pPlayer, libraryAdministration } = await createH5P(config);

    const app = express();
    // credentials: true because the H5P editor's own client-side JS makes
    // some of its Content Hub fetch() calls with `credentials: 'include'` —
    // without Access-Control-Allow-Credentials the browser blocks those.
    app.use(cors({ origin: env.corsOrigin.length > 0 ? env.corsOrigin : false, credentials: true }));
    app.use(express.json({ limit: '50mb' }));
    // The H5P editor's own client-side JS posts some of its internal ajax
    // calls (e.g. action=libraries, used to populate "which content types
    // can I add here" dropdowns) as application/x-www-form-urlencoded, not
    // JSON — without this, req.body is empty for those and every such call
    // 400s as "malformed-request", leaving the caller's dropdown stuck
    // showing "Loading...".
    app.use(express.urlencoded({ extended: true, limit: '50mb' }));

    // Unauthenticated: liveness check for Docker/orchestration.
    app.use(createHealthRouter());

    // The stock H5P ajax/core/editor/content-file router. This is what the
    // <h5p-editor>/<h5p-player> web components load and call directly from
    // the browser — it serves the legacy jQuery core/editor JS+CSS, library
    // files, content files and the many small internal ajax actions the H5P
    // client makes while authoring (semantics, translations, file uploads
    // inside editor fields). It has no auth concept of its own; the H5P
    // client-side JS has no hook for attaching custom headers to these
    // calls, so this boundary relies on the CORS allow-list (CORS_ORIGIN)
    // rather than the shared API key — unlike our own /api routes below,
    // which every caller (Laravel, and our own Vue fetch calls) can and does
    // send the key on.
    app.use(fileUpload({ useTempFiles: true, tempFileDir: env.uploadsPath }));
    app.use('/h5p', attachServiceUser, h5pAjaxExpressRouter(h5pEditor, env.corePath, env.editorPath));

    // Our own clean REST layer: Laravel's H5PService and the Vue app's
    // fetch calls both authenticate with the shared secret.
    app.use('/api/content', requireApiKey, createContentRouter(h5pEditor, h5pPlayer));
    app.use('/api/libraries', requireApiKey, createLibrariesRouter(h5pEditor, libraryAdministration));

    app.listen(env.port, () => {
        console.log(`H5P server listening on port ${env.port}`);
    });
}

main().catch((error) => {
    console.error('Failed to start H5P server', error);
    process.exit(1);
});
