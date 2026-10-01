import path from 'node:path';

/**
 * The environment the Playwright suite runs the application in: a real
 * PHP server on its own port, with its own SQLite database, so the dev
 * database is never touched.
 *
 * These are PROCESS environment variables: Laravel never overwrites a
 * variable that already exists in the environment, so they win over
 * `.env` for both the server and the migrate command — no extra `.env`
 * file. That only holds while the config is not cached (global-setup
 * refuses to run when it is).
 */
export const port = 8989;

/** School and applicant pages. */
export const centralUrl = `http://localhost:${port}`;

/** Provider console. Chromium resolves `*.localhost` to loopback. */
export const consoleUrl = `http://console.localhost:${port}`;

export const databasePath = path.resolve('database', 'e2e.sqlite');

export const appEnv: Record<string, string> = {
    // `local` on purpose: DatabaseSeeder seeds the dev tenants, the
    // provider login and the pending application only in local.
    APP_ENV: 'local',
    APP_URL: centralUrl,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: databasePath,
    MAIL_MAILER: 'log',
    QUEUE_CONNECTION: 'sync',
    TENANCY_CONSOLE_DOMAIN: 'console.localhost',
    TENANCY_URL_PORT: String(port),
};

/** Accounts created by PlatformDevSeeder. */
export const accounts = {
    provider: { email: 'admin@simas.com', password: 'admin123' },
    applicant: {
        email: 'kepsek@sekolah-c.test',
        password: 'password',
        school: 'SMA Sekolah C',
    },
};
