import { execFileSync } from 'node:child_process';

import { databasePath } from './env';

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
