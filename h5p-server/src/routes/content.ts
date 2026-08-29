import { Router, Request, Response, NextFunction } from 'express';
import multer from 'multer';
import { H5PEditor, H5PPlayer, LibraryName } from '@lumieducation/h5p-server';
import { env } from '../config';
import { serviceUser } from '../auth';

const asyncHandler =
    (fn: (req: Request, res: Response) => Promise<void>) =>
    (req: Request, res: Response, next: NextFunction): void => {
        fn(req, res).catch(next);
    };

export function createContentRouter(h5pEditor: H5PEditor, h5pPlayer: H5PPlayer): Router {
    const router = Router();
    const upload = multer({ dest: env.uploadsPath });
    const user = serviceUser;

    // List all content, newest concerns aside — just id + display metadata.
    router.get(
        '/',
        asyncHandler(async (_req, res) => {
            const ids = await h5pEditor.contentManager.listContent(user);
            const items = await Promise.all(
                ids.map(async (id) => {
                    const metadata = await h5pEditor.contentManager.getContentMetadata(id, user);
                    return {
                        id,
                        title: metadata.title,
                        mainLibrary: metadata.mainLibrary,
                        language: metadata.language
                    };
                })
            );
            res.json(items);
        })
    );

    // Editor model for brand-new, not-yet-saved content.
    router.get(
        '/editor-model',
        asyncHandler(async (_req, res) => {
            const model = await h5pEditor.render(undefined as unknown as string, 'en', user);
            res.json(model);
        })
    );

    // Editor model for existing content: the render() integration model plus
    // the currently stored parameters/metadata, exactly what
    // <h5p-editor>'s loadContentCallback expects.
    router.get(
        '/:contentId/editor-model',
        asyncHandler(async (req, res) => {
            const { contentId } = req.params;
            const [model, content] = await Promise.all([
                h5pEditor.render(contentId, 'en', user),
                h5pEditor.getContent(contentId, user)
            ]);
            // <h5p-editor>'s loadContentCallback expects library/metadata/params
            // as top-level keys alongside the render() integration model, not
            // nested — see H5PEditorComponent's loadContentCallback type.
            res.json({
                ...model,
                library: content.library,
                metadata: content.h5p,
                params: content.params.params
            });
        })
    );

    // Player model for existing content, consumed by <h5p-player>.
    router.get(
        '/:contentId/player-model',
        asyncHandler(async (req, res) => {
            const { contentId } = req.params;
            const model = await h5pPlayer.render(contentId, user, 'en');
            res.json(model);
        })
    );

    // Lightweight single-content summary — for callers (e.g. Laravel showing
    // a lesson block's H5P summary) that just need display metadata, not the
    // full render model that editor-model/player-model carry. Registered
    // after the more specific literal routes above (e.g. /editor-model),
    // since this single-segment :contentId pattern would otherwise swallow
    // them.
    router.get(
        '/:contentId',
        asyncHandler(async (req, res) => {
            const { contentId } = req.params;
            const metadata = await h5pEditor.contentManager.getContentMetadata(contentId, user);
            res.json({
                id: contentId,
                title: metadata.title,
                mainLibrary: metadata.mainLibrary,
                language: metadata.language
            });
        })
    );

    // Create new content.
    router.post(
        '/',
        asyncHandler(async (req, res) => {
            const { parameters, metadata, mainLibraryUbername } = req.body;
            const result = await h5pEditor.saveOrUpdateContentReturnMetaData(
                undefined as unknown as string,
                parameters,
                metadata,
                mainLibraryUbername,
                user
            );
            res.status(201).json(result);
        })
    );

    // Update existing content.
    router.patch(
        '/:contentId',
        asyncHandler(async (req, res) => {
            const { contentId } = req.params;
            const { parameters, metadata, mainLibraryUbername } = req.body;
            const result = await h5pEditor.saveOrUpdateContentReturnMetaData(
                contentId,
                parameters,
                metadata,
                mainLibraryUbername,
                user
            );
            res.json(result);
        })
    );

    router.delete(
        '/:contentId',
        asyncHandler(async (req, res) => {
            await h5pEditor.deleteContent(req.params.contentId, user);
            res.status(204).send();
        })
    );

    // Export the content as a downloadable .h5p package.
    router.get(
        '/:contentId/export',
        asyncHandler(async (req, res) => {
            const { contentId } = req.params;
            res.setHeader('Content-Type', 'application/octet-stream');
            res.setHeader('Content-Disposition', `attachment; filename="${contentId}.h5p"`);
            await h5pEditor.exportContent(contentId, res, user);
        })
    );

    // Import an existing .h5p package as new content (secondary workflow —
    // authoring in the editor is primary, uploading a package is supported
    // for migrating/reusing content authored elsewhere).
    router.post(
        '/import',
        upload.single('file'),
        asyncHandler(async (req, res) => {
            if (!req.file) {
                res.status(400).json({ message: 'No file uploaded.' });
                return;
            }

            const { metadata, parameters } = await h5pEditor.uploadPackage(req.file.path, user);

            if (!metadata || parameters === undefined) {
                res.status(422).json({ message: 'The uploaded package did not contain valid content.' });
                return;
            }

            const mainLibraryUbername = LibraryName.toUberName(
                metadata.preloadedDependencies.find((dependency) => dependency.machineName === metadata.mainLibrary) ?? {
                    machineName: metadata.mainLibrary,
                    majorVersion: 1,
                    minorVersion: 0
                },
                { useWhitespace: true }
            );

            const result = await h5pEditor.saveOrUpdateContentReturnMetaData(
                undefined as unknown as string,
                parameters,
                metadata,
                mainLibraryUbername,
                user
            );

            res.status(201).json(result);
        })
    );

    return router;
}
