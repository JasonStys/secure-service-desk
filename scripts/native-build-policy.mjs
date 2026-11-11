/**
 * File: scripts/native-build-policy.mjs
 * Purpose: Give trusted Composer build work a finite CLI budget separate from runtime CGI.
 * Functions: composerInstallArguments constructs only fixed production-install argv; code-index.md.
 * State: explicit SDK/PHAR paths, build-only120 seconds, existing outer180 seconds; no global ini.
 */
import { phpArguments } from '../desktop/runtime.mjs';

export const COMPOSER_PHP_SECONDS = 120;

/** Override only this Composer process; runtime PHP keeps its original30-second request limit. */
export function composerInstallArguments(phpDirectory, composer) {
    return [
        ...phpArguments(phpDirectory),
        '-d',
        'extension=zip',
        '-d',
        'extension=curl',
        '-d',
        'allow_url_fopen=1',
        '-d',
        `max_execution_time=${COMPOSER_PHP_SECONDS}`,
        composer,
        'install',
        '--no-dev',
        '--no-scripts',
        '--no-plugins',
        '--no-interaction',
        '--prefer-dist',
        '--no-progress',
    ];
}
