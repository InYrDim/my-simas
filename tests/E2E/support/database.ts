import { execFileSync } from 'node:child_process';

import { appEnv, databasePath } from './env';

/**
 * Fills the seeded schools with Core's demo records (academic years,
 * classes, teachers, students, teaching assignments) — the same seeder a
 * developer runs by hand. It skips a school that already has an academic
 * year, so calling it from several specs is safe.
 */
export function seedDemoSchoolData(): void {
    execFileSync(
        'php',
        [
            'artisan',
            'db:seed',
            '--class=Modules\\Core\\Database\\Seeders\\CoreDemoSeeder',
            '--force',
            '--no-interaction',
        ],
        { env: { ...process.env, ...appEnv }, stdio: 'ignore' },
    );
}

/**
 * The school code (= tenant id) of a seeded school. It is generated anew
 * by every seed, so a spec that logs in to a school reads it from the e2e
 * database. Plain PDO: no framework boot, and nothing but a read.
 */
export function schoolCode(slug: string): string {
    const code = execFileSync(
        'php',
        [
            '-r',
            '$query = (new PDO("sqlite:".$argv[1]))->prepare("select id from tenants where slug = ?"); $query->execute([$argv[2]]); echo $query->fetchColumn();',
            databasePath,
            slug,
        ],
        { encoding: 'utf8' },
    ).trim();

    if (code === '') {
        throw new Error(`No seeded school with slug [${slug}] in ${databasePath}.`);
    }

    return code;
}
