import path from 'path';
import {
    H5PEditor,
    H5PPlayer,
    H5PConfig,
    LaissezFairePermissionSystem,
    LibraryAdministration,
    fsImplementations,
    IEditorModel,
    IPlayerModel
} from '@lumieducation/h5p-server';
import { env } from './config';

const { FileLibraryStorage, FileContentStorage, DirectoryTemporaryFileStorage, InMemoryStorage } = fsImplementations;

/**
 * Both H5PEditor and H5PPlayer render() calls default to returning a full
 * HTML page (meant for classic server-rendered use). Passing an identity
 * function to setRenderer() makes them return the raw IEditorModel /
 * IPlayerModel objects instead, which is the documented pattern for
 * REST + webcomponents integrations (our Vue app renders the model itself
 * via <h5p-editor>/<h5p-player>).
 */
const identityRenderer = <T>(model: T): T => model;

export async function createH5P(config: H5PConfig) {
    const libraryStorage = new FileLibraryStorage(path.join(env.storagePath, 'libraries'));
    const contentStorage = new FileContentStorage(path.join(env.storagePath, 'content'));
    const temporaryStorage = new DirectoryTemporaryFileStorage(path.join(env.storagePath, 'temporary'));
    const cache = new InMemoryStorage();
    const permissionSystem = new LaissezFairePermissionSystem();

    const h5pEditor = new H5PEditor(
        cache,
        config,
        libraryStorage,
        contentStorage,
        temporaryStorage,
        undefined,
        undefined,
        { permissionSystem }
    );
    h5pEditor.setRenderer(identityRenderer<IEditorModel>);

    const h5pPlayer = new H5PPlayer(
        libraryStorage,
        contentStorage,
        config,
        undefined,
        undefined,
        undefined,
        { permissionSystem }
    );
    h5pPlayer.setRenderer(identityRenderer<IPlayerModel>);

    const libraryAdministration = new LibraryAdministration(h5pEditor.libraryManager, h5pEditor.contentManager);

    return { h5pEditor, h5pPlayer, permissionSystem, libraryAdministration };
}
