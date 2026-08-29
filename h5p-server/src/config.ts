import fs from 'fs';
import path from 'path';
import { H5PConfig, fsImplementations } from '@lumieducation/h5p-server';

const { JsonStorage } = fsImplementations;

export const env = {
    port: parseInt(process.env.PORT ?? '8080', 10),
    serverUrl: process.env.H5P_SERVER_URL ?? 'http://127.0.0.1:8080',
    // What the *browser* can reach this service at — used to build
    // absolute asset/ajax URLs (see baseUrl below). In Docker this differs
    // from H5P_SERVER_URL, which is the container-to-container address.
    publicUrl: process.env.H5P_SERVER_PUBLIC_URL ?? process.env.H5P_SERVER_URL ?? 'http://127.0.0.1:8080',
    apiKey: process.env.H5P_SERVER_API_KEY ?? '',
    storagePath: path.resolve(process.env.H5P_STORAGE_PATH ?? './storage'),
    uploadsPath: path.resolve(process.env.H5P_UPLOADS_PATH ?? './uploads'),
    corePath: path.resolve(process.env.H5P_CORE_PATH ?? './h5p/core'),
    editorPath: path.resolve(process.env.H5P_EDITOR_PATH ?? './h5p/editor'),
    corsOrigin: (process.env.CORS_ORIGIN ?? '').split(',').map((origin) => origin.trim()).filter(Boolean)
};

/**
 * baseUrl must be an *absolute* URL, not the relative '/h5p' default: the
 * browser (running on Laravel's origin) loads H5P's core/editor scripts and
 * makes ajax calls directly against this service, on a different origin —
 * every asset/ajax URL the H5P server embeds in its responses is built as
 * `${baseUrl}${xUrl}` (see UrlGenerator), so a relative baseUrl would
 * resolve against the *page's* origin instead of this one. index.ts mounts
 * the ajax router at '/h5p', matching the '/h5p' suffix here.
 */
export async function createConfig(): Promise<H5PConfig> {
    const configFile = path.join(env.storagePath, 'config.json');
    fs.mkdirSync(env.storagePath, { recursive: true });
    if (!fs.existsSync(configFile)) {
        fs.writeFileSync(configFile, '{}');
    }
    const configStorage = await JsonStorage.create(configFile);

    const config = new H5PConfig(configStorage, {
        baseUrl: `${env.publicUrl}/h5p`,
        siteType: 'local'
    });

    await config.load();

    return config;
}
