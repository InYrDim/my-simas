import { expect, test } from '@playwright/test';

import {
    schoolCode,
    seedDemoSchoolData,
    studentRecord,
} from './support/database';
import { accounts, centralUrl } from './support/env';

/**
 * A student's first login on the real server: the account an admin made
 * signs in with NIS and birth date, is held on the change-password page
 * by a session that is reloaded on every request, and afterwards only the
 * new password works. The Pest browser suite serves every request from
 * one PHP process, where a guard keeps the user it loaded — so the
 * per-request flag is what this spec proves. The dialogs and who gets
 * skipped are covered in tests/Browser and the Core feature tests.
 *
 * Uses the demo records of `sekolah-a` (seeds them when insight.spec.ts
 * has not) and gives the students of one class an account; later specs do
 * not look at accounts.
 */
const nis = '240100';
const newPassword = 'SandiBaruSaya1!';

// Password hashing at full cost on a single-threaded server takes a while.
const slow = 30_000;

test.beforeAll(() => seedDemoSchoolData());

test('a student signs in with NIS and birth date and has to choose a new password', async ({
    browser,
}) => {
    // Three logins, a class of accounts and a password change, all hashed at full cost.
    test.setTimeout(180_000);

    const code = schoolCode(accounts.schoolAdmin.schoolSlug);
    const student = studentRecord(accounts.schoolAdmin.schoolSlug, nis);

    // --- The admin makes the accounts of the student's class
    const adminContext = await browser.newContext();
    const admin = await adminContext.newPage();

    await admin.goto(`${centralUrl}/login`);
    await admin.getByLabel('Kode sekolah').fill(code);
    await admin
        .getByLabel('Email atau NIS/NIP')
        .fill(accounts.schoolAdmin.email);
    await admin.getByLabel('Kata sandi').fill(accounts.schoolAdmin.password);
    await admin.getByRole('button', { name: 'Masuk' }).click();
    await expect(admin).toHaveURL(`${centralUrl}/beranda`, { timeout: 30_000 });

    await admin.goto(`${centralUrl}/master/kelas/${student.classId}`);
    await admin.getByRole('button', { name: 'Buatkan akun siswa' }).click();
    await admin
        .getByRole('button', { name: 'Buatkan akun', exact: true })
        .click();
    await expect(admin.getByText(/akun dibuat\./)).toBeVisible({
        timeout: slow,
    });

    await adminContext.close();

    // --- The student, in a browser of their own
    const studentContext = await browser.newContext();
    const page = await studentContext.newPage();

    await page.goto(`${centralUrl}/login`);
    await page.getByLabel('Kode sekolah').fill(code);
    await page.getByLabel('Email atau NIS/NIP').fill(nis);
    await page.getByLabel('Kata sandi').fill(student.birthPassword);
    await page.getByRole('button', { name: 'Masuk' }).click();

    await expect(page).toHaveURL(`${centralUrl}/ganti-kata-sandi`, {
        timeout: slow,
    });

    // Every other page leads back here until the password is changed.
    await page.goto(`${centralUrl}/beranda`);
    await expect(page).toHaveURL(`${centralUrl}/ganti-kata-sandi`);

    await page.getByLabel('Kata sandi sekarang').fill(student.birthPassword);
    await page.getByLabel('Kata sandi baru', { exact: true }).fill(newPassword);
    await page.getByLabel('Ulangi kata sandi baru').fill(newPassword);
    await page.getByRole('button', { name: 'Simpan kata sandi' }).click();

    await expect(page).toHaveURL(`${centralUrl}/beranda`, { timeout: slow });
    await expect(page.getByRole('main').getByText(student.name)).toBeVisible();

    await studentContext.close();

    // --- A new session: the birth date no longer opens the account
    const laterContext = await browser.newContext();
    const later = await laterContext.newPage();

    await later.goto(`${centralUrl}/login`);
    await later.getByLabel('Kode sekolah').fill(code);
    await later.getByLabel('Email atau NIS/NIP').fill(nis);
    await later.getByLabel('Kata sandi').fill(student.birthPassword);
    await later.getByRole('button', { name: 'Masuk' }).click();
    await expect(
        later.getByText('These credentials do not match our records.'),
    ).toBeVisible({ timeout: slow });
    await expect(later).toHaveURL(`${centralUrl}/login`);

    await later.getByLabel('Kode sekolah').fill(code);
    await later.getByLabel('Email atau NIS/NIP').fill(nis);
    await later.getByLabel('Kata sandi').fill(newPassword);
    await later.getByRole('button', { name: 'Masuk' }).click();
    await expect(later).toHaveURL(`${centralUrl}/beranda`, { timeout: slow });

    await laterContext.close();
});
