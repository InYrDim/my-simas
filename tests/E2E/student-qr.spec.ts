import { expect, test } from '@playwright/test';

import {
    schoolCode,
    seedDemoSchoolData,
    studentRecord,
} from './support/database';
import { accounts, centralUrl } from './support/env';

/**
 * A student's attendance QR on the real server: the code a student's page
 * asks for in one request is read by the scanner in another person's
 * request, and works exactly once. The Pest browser suite serves every
 * request from one PHP process and one in-memory cache, so it cannot
 * prove that the code crosses requests and sessions — this spec does. The
 * rules (late cut-off, refusals, permissions) are covered by the
 * Attendance feature tests, the scanner page by tests/Browser.
 *
 * Uses the demo records of `sekolah-a` (seeds them when an earlier spec
 * has not) and gives one class its accounts. It records an arrival for one
 * student today; nothing later looks at attendance.
 */
const nis = '240101';
const newPassword = 'SandiQrSaya1!';

// Password hashing at full cost on a single-threaded server takes a while.
const slow = 30_000;

test.beforeAll(() => seedDemoSchoolData());

test('a student shows a one-time QR code and the gate records it once', async ({
    browser,
}) => {
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
    await expect(admin).toHaveURL(`${centralUrl}/beranda`, { timeout: slow });

    await admin.goto(`${centralUrl}/master/kelas/${student.classId}`);
    await admin.getByRole('button', { name: 'Buatkan akun siswa' }).click();
    await admin
        .getByRole('button', { name: 'Buatkan akun', exact: true })
        .click();
    // Another spec may have made them already.
    await expect(admin.getByText(/akun dibuat\.|sudah punya akun/)).toBeVisible(
        { timeout: slow },
    );

    // --- The student signs in and chooses a password
    const studentContext = await browser.newContext();
    const page = await studentContext.newPage();
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    await page.goto(`${centralUrl}/login`);
    await page.getByLabel('Kode sekolah').fill(code);
    await page.getByLabel('Email atau NIS/NIP').fill(nis);
    await page.getByLabel('Kata sandi').fill(student.birthPassword);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(`${centralUrl}/ganti-kata-sandi`, {
        timeout: slow,
    });

    await page.getByLabel('Kata sandi sekarang').fill(student.birthPassword);
    await page.getByLabel('Kata sandi baru', { exact: true }).fill(newPassword);
    await page.getByLabel('Ulangi kata sandi baru').fill(newPassword);
    await page.getByRole('button', { name: 'Simpan kata sandi' }).click();
    await expect(page).toHaveURL(`${centralUrl}/beranda`, { timeout: slow });

    // --- The student's menu holds the QR page and nothing else of Absensi
    await expect(page.getByRole('link', { name: 'QR Absensi' })).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Rekap Hari Ini' }),
    ).toHaveCount(0);

    await page.getByRole('link', { name: 'QR Absensi' }).click();
    await expect(page).toHaveURL(`${centralUrl}/absensi/qr-saya`, {
        timeout: slow,
    });

    const qr = page.getByTestId('attendance-qr');
    await expect(qr).toBeVisible({ timeout: slow });
    await expect(qr.locator('svg')).toBeVisible();
    await expect(page.getByText('Belum tercatat')).toBeVisible();

    const firstCode = await qr.getAttribute('data-code');
    expect(firstCode).toMatch(/^[A-Za-z0-9]{40}$/);

    // A new visit is a new code; the old one is dead.
    await page.reload();
    await expect(qr).toBeVisible({ timeout: slow });
    const liveCode = await qr.getAttribute('data-code');
    expect(liveCode).toMatch(/^[A-Za-z0-9]{40}$/);
    expect(liveCode).not.toBe(firstCode);

    // --- The gate: the replaced code is refused, the live one records once
    await admin.goto(`${centralUrl}/absensi/pindai`);
    const codeField = admin.getByLabel('Kode', { exact: true });
    const record = admin.getByRole('button', { name: 'Catat', exact: true });

    await codeField.fill(firstCode as string);
    await record.click();
    await expect(
        admin.getByText('Kode QR tidak dikenal atau sudah kedaluwarsa'),
    ).toBeVisible();

    await codeField.fill(liveCode as string);
    await record.click();
    await expect(admin.getByText(student.name)).toBeVisible();
    await expect(admin.getByText('Tercatat', { exact: true })).toBeVisible();

    await codeField.fill(liveCode as string);
    await record.click();
    await expect(
        admin.getByText('Kode QR tidak dikenal atau sudah kedaluwarsa'),
    ).toHaveCount(2);

    // --- The student sees the arrival on the QR page
    await page.reload();
    await expect(page.getByText('Belum tercatat')).toHaveCount(0);
    await expect(page.getByText(/^(Hadir|Terlambat)$/)).toBeVisible();

    // The staff pages stay closed to a student.
    const overview = await page.goto(`${centralUrl}/absensi`);
    expect(overview?.status()).toBe(403);

    expect(errors).toEqual([]);

    await studentContext.close();
    await adminContext.close();
});
