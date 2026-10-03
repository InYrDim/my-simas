import { expect, test } from '@playwright/test';

import { schoolCode } from './support/database';
import { accounts, centralUrl } from './support/env';

/**
 * A CSV upload through the real server: the page sends the file as
 * multipart, PHP parses it, and the import answers with JSON. The Pest
 * browser suite cannot prove this — its in-process server does not parse
 * multipart bodies — so this one flow lives here. The row checks
 * themselves are covered by the Core feature tests.
 *
 * Adds one student to the seeded school `sekolah-a`; nothing else in the
 * suite depends on that school's students.
 */
test('a school admin imports students from a CSV file', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    await page.goto('/login');
    await page
        .getByLabel('Kode sekolah')
        .fill(schoolCode(accounts.schoolAdmin.schoolSlug));
    await page.getByLabel('Email').fill(accounts.schoolAdmin.email);
    await page.getByLabel('Kata sandi').fill(accounts.schoolAdmin.password);
    await page.getByRole('button', { name: 'Masuk' }).click();
    // This spec runs first: the login is the cold server's first real work.
    await expect(page).toHaveURL(`${centralUrl}/beranda`, { timeout: 30_000 });

    await page.goto('/kelola/impor');
    await expect(
        page.getByRole('button', { name: 'Lanjut ke pratinjau' }),
    ).toBeDisabled();

    // The way a spreadsheet saves it: `;` separated behind a `sep=` line.
    // The second row names a class the school does not have.
    await page.getByLabel('Berkas CSV').setInputFiles({
        name: 'siswa.csv',
        mimeType: 'text/csv',
        buffer: Buffer.from(
            [
                'sep=;',
                'nama;nis;nisn;jenis_kelamin;tanggal_lahir;nama_wali;telepon_wali;kelas',
                'Playwright Santoso;PW-0071;;L;17/05/2010;;;',
                'Playwright Dewi;PW-0072;;P;;;;Kelas Tidak Ada',
                '',
            ].join('\r\n'),
        ),
    });
    await page.getByRole('button', { name: 'Lanjut ke pratinjau' }).click();

    await expect(
        page.getByText(
            '2 baris terbaca: 1 baru, 0 diperbarui, 1 perlu diperbaiki.',
        ),
    ).toBeVisible();
    await expect(
        page.getByText(
            'Kelas "Kelas Tidak Ada" tidak ada di tahun ajaran aktif.',
        ),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Impor 1 baris yang siap' }).click();
    await expect(
        page.getByText('1 baris ditambahkan, 0 diperbarui, 1 dilewati.'),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Lihat daftar Siswa' }).click();
    await expect(page).toHaveURL(`${centralUrl}/master/siswa`);
    await expect(page.getByText('Playwright Santoso')).toBeVisible();
    await expect(page.getByText('PW-0071')).toBeVisible();
    await expect(page.getByText('Playwright Dewi')).toHaveCount(0);

    expect(errors).toEqual([]);
});
