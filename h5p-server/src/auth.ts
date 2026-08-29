import { Request, Response, NextFunction } from 'express';
import { IUser } from '@lumieducation/h5p-server';
import { env } from './config';

/**
 * This service has no concept of tutors, courses or ownership — Laravel
 * checks per-user authorization (does this tutor own the LessonBlock this
 * content is linked to) before ever handing the browser a content id or a
 * "create new" session. This middleware only verifies the caller (Laravel's
 * backend, or the browser widgets acting on a tutor's behalf) holds the
 * shared secret both sides are configured with.
 */
export function requireApiKey(req: Request, res: Response, next: NextFunction): void {
    const provided = req.header('X-H5P-Api-Key');

    if (!env.apiKey || provided !== env.apiKey) {
        res.status(401).json({ message: 'Invalid or missing API key.' });
        return;
    }

    next();
}

/**
 * H5P's storage/permission APIs require *a* IUser object even though the
 * permission system in use (LaissezFairePermissionSystem) allows everything
 * to everyone. Laravel doesn't currently pass through a real tutor identity,
 * so every request is attributed to this single service-account user; H5P
 * content is a shared library resource, not scoped per-tutor.
 */
export const serviceUser: IUser = {
    id: 'laravel-service-account',
    name: 'Learning Platform',
    email: 'h5p-service@learning-platform.local',
    type: 'local'
};

/**
 * @lumieducation/h5p-express's stock ajax router reads req.user directly
 * (see IRequestWithUser); it has no auth concept of its own.
 */
export function attachServiceUser(req: Request & { user?: IUser }, _res: Response, next: NextFunction): void {
    req.user = serviceUser;
    next();
}
