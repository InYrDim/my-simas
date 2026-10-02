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

/**
 * A seeded student of a school: the class to open, the name to look for
 * and the first password of the account (the birth date as ddmmyyyy).
 */
export function studentRecord(slug: string, nis: string): { name: string; classId: number; birthPassword: string } {
    const row = execFileSync(
        'php',
        [
            '-r',
            '$query = (new PDO("sqlite:".$argv[1]))->prepare("select s.name, s.class_id, s.birth_date from students s join tenants t on t.id = s.tenant_id where t.slug = ? and s.nis = ?"); $query->execute([$argv[2], $argv[3]]); echo json_encode($query->fetch(PDO::FETCH_ASSOC));',
            databasePath,
            slug,
            nis,
        ],
        { encoding: 'utf8' },
    ).trim();

    const student = JSON.parse(row) as { name: string; class_id: number | null; birth_date: string | null } | false;

    if (student === false || student.class_id === null || student.birth_date === null) {
        throw new Error(`No seeded student [${nis}] with a class and a birth date in school [${slug}].`);
    }

    const [year, month, day] = student.birth_date.slice(0, 10).split('-');

    return { name: student.name, classId: Number(student.class_id), birthPassword: `${day}${month}${year}` };
}
