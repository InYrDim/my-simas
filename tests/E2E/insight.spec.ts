import fs from 'node:fs';

import { expect, test } from '@playwright/test';

import { schoolCode, seedDemoSchoolData } from './support/database';
import { accounts, centralUrl } from './support/env';

/**
 * Statistik & Laporan through the real server: a report leaves the server
 * as a streamed file download, and the print view opens in a tab of its
 * own. The Pest browser suite cannot prove either — its in-process server
 * hands back no file, and a test page does not follow a new tab. What each
 * report holds is covered by the Core feature tests.
 *
 * Seeds Core's demo records into the seeded schools (academic years,
 * classes, students). Runs after import.spec.ts, which needs `sekolah-a`
 * without classes; the later specs do not look at school records.
 */
test.beforeAll(() => seedDemoSchoolData());

test('a school admin reads the statistics, downloads a report and opens its print view', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    await page.goto('/login');
    await page.getByLabel('Kode sekolah').fill(schoolCode(accounts.schoolAdmin.schoolSlug));
    await page.getByLabel('Email').fill(accounts.schoolAdmin.email);
    await page.getByLabel('Kata sandi').fill(accounts.schoolAdmin.password);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(`${centralUrl}/beranda`, { timeout: 30_000 });

    await page.goto('/statistik-laporan/statistik');
    await expect(page.getByText('Tampilan contoh')).toHaveCount(0);
    await expect(page.getByText('Gambaran singkat sekolah pada tahun ajaran 2025/2026.')).toBeVisible();
    await expect(page.getByText('Siswa aktif', { exact: true })).toBeVisible();
    await expect(page.getByText('Siswa per tingkat')).toBeVisible();
    // The school uses Absensi, so its figure is real, not announced.
    await expect(page.getByText('Rata-rata kehadiran')).toBeVisible();

    await page.goto('/statistik-laporan/laporan');
    await expect(page.getByRole('combobox', { name: 'Tahun ajaran' })).toContainText('2025/2026 (aktif)');

    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: 'Unduh CSV Daftar Siswa per Kelas' }).click(),
    ]);

    expect(download.suggestedFilename()).toBe('student-list-2025-2026.csv');

    const csv = fs.readFileSync(await download.path(), 'utf8');

    expect(csv.startsWith('sep=;\r\nKelas;NIS;NISN;Nama;L/P;Keterangan\r\n')).toBe(true);
    expect(csv).toContain('"Aditya Nugraha"');

    const [printTab] = await Promise.all([
        page.waitForEvent('popup'),
        page.getByRole('link', { name: 'Cetak Beban Mengajar Guru' }).click(),
    ]);

    await expect(printTab).toHaveURL(
        new RegExp(`^${centralUrl}/statistik-laporan/laporan/teaching-load/cetak\\?tahun=\\d+$`),
    );
    await expect(printTab.getByRole('heading', { name: 'Beban Mengajar Guru' })).toBeVisible();
    await expect(printTab.getByText('Tahun ajaran 2025/2026')).toBeVisible();
    await expect(printTab.getByRole('columnheader', { name: 'Jam per minggu' })).toBeVisible();
    await expect(printTab.getByRole('button', { name: 'Cetak' })).toBeVisible();

    expect(errors).toEqual([]);
});
