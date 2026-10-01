import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

import { appEnv, databasePath } from './support/env';

/**
 * Runs once before the suite: a fresh, seeded database of its own.
 *
 * `migrate:fresh` drops every table, so this must never reach the dev
 * database. Two guards: the target is the dedicated e2e file, and a
 * cached config (which would ignore the environment and point at
 * whatever `.env` said when it was cached) aborts the run.
 */
export default function globalSetup(): void {
    if (fs.existsSync(path.resolve('bootstrap', 'cache', 'config.php'))) {
        throw new Error(
            'The config is cached, so the e2e environment would be ignored. Run `php artisan config:clear` first.',
        );
    }

    if (path.basename(databasePath) !== 'e2e.sqlite') {
        throw new Error(`Refusing to reset ${databasePath}: not the e2e database.`);
    }

    fs.mkdirSync(path.dirname(databasePath), { recursive: true });
    fs.writeFileSync(databasePath, '');

    execSync('php artisan migrate:fresh --seed --force --no-interaction', {
        env: { ...process.env, ...appEnv },
        stdio: 'inherit',
    });
}
