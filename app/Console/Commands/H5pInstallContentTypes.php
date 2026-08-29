<?php

namespace App\Console\Commands;

use App\Services\H5p\H5PKernel;
use Illuminate\Console\Command;

/**
 * Laravel equivalent of the old h5p-server/scripts/install-content-types.ts
 * — a one-time (or re-run-anytime) setup script that installs a small,
 * useful starter set of H5P content types from the official H5P Hub onto a
 * fresh server. The editor's own "Add content" screen only lists whatever's
 * actually installed, so this doesn't need to cover every content type —
 * just get a useful default set in place; tutors (or an admin, via the same
 * install action this drives) can add more from the Hub afterwards.
 */
class H5pInstallContentTypes extends Command
{
    protected $signature = 'h5p:install-content-types {types?* : Machine names to install (defaults to the starter set)}';

    protected $description = 'Install a starter set of H5P content types from the official H5P Hub.';

    /** @var string[] */
    protected array $starterSet = [
        'H5P.Accordion',
        'H5P.CoursePresentation',
        'H5P.InteractiveVideo',
        'H5P.MultiChoice',
        'H5P.Blanks',
    ];

    public function handle(H5PKernel $kernel): int
    {
        $machineNames = $this->argument('types') ?: $this->starterSet;

        $editor = $kernel->editor();
        $ajax = $editor->ajax;

        // H5PEditorAjax::action()'s LIBRARY_INSTALL case checks
        // $_SERVER['REQUEST_METHOD'] === 'POST' — unset in a console
        // context, so it would otherwise silently no-op.
        $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $failures = [];

        foreach ($machineNames as $machineName) {
            $this->info("Installing {$machineName}...");

            ob_start();
            try {
                $ajax->action('library-install', 'console', $machineName);
            } finally {
                $body = ob_get_clean();
            }

            $result = json_decode($body, true);
            if (! ($result['success'] ?? false)) {
                $failures[] = $machineName;
                $this->error('  Failed: '.($result['message'] ?? $body));
            } else {
                $this->line('  Installed.');
            }
        }

        if ($previousMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $previousMethod;
        }

        if ($failures) {
            $this->error('Failed to install: '.implode(', ', $failures));

            return self::FAILURE;
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
