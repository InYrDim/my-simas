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
 * Opens the admissions of a seeded school: a running PPDB period with one
 * wave that is open today and a Zonasi path of two seats. The dates are
 * relative to the day the suite runs, so the form is open whenever it does.
 * Skips a school that already has a period, so calling it twice is safe.
 */
export function openAdmissions(slug: string): void {
    execFileSync(
        'php',
        [
            '-r',
            [
                '$pdo = new PDO("sqlite:".$argv[1]);',
                '$tenant = $pdo->query("select id from tenants where slug = ".$pdo->quote($argv[2]))->fetchColumn();',
                'if ($tenant === false) { fwrite(STDERR, "No school ".$argv[2]); exit(1); }',
                'if ($pdo->query("select count(*) from ppdb_periods where tenant_id = ".$pdo->quote($tenant))->fetchColumn() > 0) { exit(0); }',
                '$now = date("Y-m-d H:i:s");',
                '$pdo->prepare("insert into ppdb_periods (tenant_id, name, entry_year, status, created_at, updated_at) values (?, ?, ?, ?, ?, ?)")->execute([$tenant, "PPDB E2E", (int) date("Y") + 1, "active", $now, $now]);',
                '$period = $pdo->lastInsertId();',
                '$pdo->prepare("insert into ppdb_waves (tenant_id, period_id, name, opens_on, closes_on, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)")->execute([$tenant, $period, "Gelombang E2E", date("Y-m-d", strtotime("-2 days")), date("Y-m-d", strtotime("+30 days")), $now, $now]);',
                '$pdo->prepare("insert into ppdb_paths (tenant_id, period_id, name, quota, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)")->execute([$tenant, $period, "Zonasi", 2, 0, $now, $now]);',
            ].join(' '),
            databasePath,
            slug,
        ],
        { stdio: 'inherit' },
    );
}

/**
 * Marks an applicant's PPDB account as verified, as opening the mailed
 * link would. The mail goes to the log on this server, and the link itself
 * is covered by the feature and browser suites.
 */
export function verifyPpdbAccount(email: string): void {
    const changed = execFileSync(
        'php',
        [
            '-r',
            '$pdo = new PDO("sqlite:".$argv[1]); $statement = $pdo->prepare("update ppdb_accounts set email_verified_at = ? where email = ?"); $statement->execute([date("Y-m-d H:i:s"), $argv[2]]); echo $statement->rowCount();',
            databasePath,
            email,
        ],
        { encoding: 'utf8' },
    ).trim();

    if (changed !== '1') {
        throw new Error(`No PPDB account [${email}] to verify in ${databasePath}.`);
    }
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
