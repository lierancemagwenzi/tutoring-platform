/**
 * One-time (or re-run-anytime) setup script that installs a small, useful
 * starter set of H5P content types from the official H5P Hub. Run it once
 * after fetch-core.sh; the H5P editor's own "Add content" screen lists
 * whatever's installed, so tutors pick from that afterwards — this script
 * doesn't need to cover every content type, just get a useful default set
 * onto a fresh server.
 */
import 'dotenv/config';
import { createConfig } from '../src/config';
import { createH5P } from '../src/h5p';
import { serviceUser } from '../src/auth';

const STARTER_CONTENT_TYPES = [
    'H5P.Accordion',
    'H5P.CoursePresentation',
    'H5P.InteractiveVideo',
    'H5P.MultiChoice',
    'H5P.Blanks'
];

async function main(): Promise<void> {
    const config = await createConfig();
    const { h5pEditor } = await createH5P(config);

    console.log('Registering with the H5P Hub / refreshing content type cache...');
    await h5pEditor.contentTypeCache.forceUpdate();

    for (const machineName of STARTER_CONTENT_TYPES) {
        console.log(`Installing ${machineName}...`);
        try {
            const results = await h5pEditor.installLibraryFromHub(machineName, serviceUser);
            for (const result of results) {
                console.log(`  ${result.type}: ${result.newVersion?.machineName ?? machineName}`);
            }
        } catch (error) {
            console.error(`  Failed to install ${machineName}:`, error instanceof Error ? error.message : error);
        }
    }

    console.log('Done.');
}

main().catch((error) => {
    console.error('install-content-types failed', error);
    process.exit(1);
});
