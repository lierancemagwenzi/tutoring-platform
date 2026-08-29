import { Router, Request, Response, NextFunction } from 'express';
import { H5PEditor, LibraryAdministration } from '@lumieducation/h5p-server';
import { serviceUser } from '../auth';

const asyncHandler =
    (fn: (req: Request, res: Response) => Promise<void>) =>
    (req: Request, res: Response, next: NextFunction): void => {
        fn(req, res).catch(next);
    };

export function createLibrariesRouter(h5pEditor: H5PEditor, libraryAdministration: LibraryAdministration): Router {
    const router = Router();
    const user = serviceUser;

    // Libraries actually installed on this server (what the editor's "Add
    // content" screen can offer right now).
    router.get(
        '/',
        asyncHandler(async (_req, res) => {
            const libraries = await libraryAdministration.getLibraries();
            res.json(libraries);
        })
    );

    // The H5P Hub's content type catalogue, annotated with which of those
    // are already installed here — used to drive a "install more content
    // types" admin action, not exposed to tutors directly.
    router.get(
        '/hub',
        asyncHandler(async (_req, res) => {
            const hubInfo = await h5pEditor.getContentTypeCache(user, 'en');
            res.json(hubInfo);
        })
    );

    router.post(
        '/hub/:machineName/install',
        asyncHandler(async (req, res) => {
            const result = await h5pEditor.installLibraryFromHub(req.params.machineName, user);
            res.json(result);
        })
    );

    return router;
}
