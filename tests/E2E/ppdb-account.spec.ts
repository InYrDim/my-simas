import { expect, test } from '@playwright/test';

import {
    openAdmissions,
    schoolCode,
    verifyPpdbAccount,
} from './support/database';
import { accounts, centralUrl } from './support/env';

/**
 * An applicant's own PPDB account on the real server (Fase 11): in a
 * browser of their own they make an account, join a school with its code
 * and send the registration form; in another browser the school's admin
 * finds that registration, and neither session can open the other's pages.
 * The Pest browser suite runs one PHP process and one auth guard for every
 * request, so it cannot show that two real sessions stay apart — this spec
 * does. The rules (one registration per account, quota, results held back,
 * tenant isolation, the mailed links) are covered by the Ppdb feature
 * tests, the whole journey to a new student by tests/Browser/PpdbTest.php.
 *
 * Uses `sekolah-a` (PPDB is switched on for it by the dev seeder) and gives
 * it a running period; nothing later looks at admissions.
 */
const email = 'siti.e2e@contoh.test';
const password = 'RahasiaSiti1!';
const childName = 'Nadia E2E Putri';

// Password hashing at full cost on a single-threaded server takes a while.
const slow = 30_000;

test.beforeAll(() => openAdmissions(accounts.schoolAdmin.schoolSlug));

test('an applicant joins a school and sends the form, and the school admin sees it', async ({
    browser,
}) => {
    test.setTimeout(180_000);

    const code = schoolCode(accounts.schoolAdmin.schoolSlug);

    // --- The applicant makes an account in a browser of their own
    const applicantContext = await browser.newContext();
    const applicant = await applicantContext.newPage();
    const errors: string[] = [];
    applicant.on('pageerror', (error) => errors.push(error.message));

    await applicant.goto(`${centralUrl}/calon-siswa/daftar`);
    await applicant.getByLabel('Nama Anda').fill('Siti Aminah');
    await applicant.getByLabel('Email', { exact: true }).fill(email);
    await applicant.getByLabel('Kata sandi', { exact: true }).fill(password);
    await applicant.getByLabel('Ulangi kata sandi').fill(password);
    await applicant.getByRole('button', { name: 'Buat akun' }).click();
    await expect(applicant).toHaveURL(`${centralUrl}/calon-siswa/verifikasi`, {
        timeout: slow,
    });

    // --- Until the email is verified the account stays at the notice
    await applicant.goto(`${centralUrl}/calon-siswa`);
    await expect(applicant).toHaveURL(`${centralUrl}/calon-siswa/verifikasi`);

    verifyPpdbAccount(email);

    // --- They join with the code from the school's link
    await applicant.goto(`${centralUrl}/calon-siswa/gabung?school=${code}`);
    await expect(applicant.getByLabel('Kode sekolah')).toHaveValue(code);
    await applicant
        .getByRole('button', { name: 'Gabung', exact: true })
        .click();
    await expect(applicant).toHaveURL(`${centralUrl}/calon-siswa`, {
        timeout: slow,
    });
    await expect(
        applicant.getByText('Anda sudah bergabung ke sekolah.'),
    ).toBeVisible();

    // --- They send the registration form
    await applicant
        .getByRole('link', { name: 'Isi formulir pendaftaran' })
        .click();
    await expect(applicant).toHaveURL(`${centralUrl}/calon-siswa/formulir`);

    await applicant.getByLabel('Nama lengkap').fill(childName);
    await applicant.getByRole('combobox', { name: 'Jalur' }).click();
    await applicant.getByRole('option', { name: 'Zonasi' }).click();
    await applicant.getByRole('combobox', { name: 'Jenis kelamin' }).click();
    await applicant.getByRole('option', { name: 'Perempuan' }).click();
    await applicant.getByLabel('Tanggal lahir').fill('2012-05-04');
    await applicant.getByLabel('Asal sekolah').fill('SMPN 3 Bandung');
    await applicant.getByLabel('Nama wali').fill('Budi Santoso');
    await applicant.getByLabel('Telepon wali').fill('081234567890');
    await applicant.getByRole('button', { name: 'Kirim pendaftaran' }).click();

    await expect(applicant).toHaveURL(`${centralUrl}/calon-siswa`, {
        timeout: slow,
    });
    await expect(
        applicant.getByText(
            /Pendaftaran terkirim\. Nomor pendaftaran Anda: PPDB-\d{2}-0001\./,
        ),
    ).toBeVisible();
    await expect(applicant.getByText('Menunggu verifikasi')).toBeVisible();
    await expect(
        applicant.getByText('Hasil seleksi belum diumumkan'),
    ).toBeVisible();

    // --- The school admin, in another browser, finds the registration
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

    await admin.goto(`${centralUrl}/ppdb/pendaftar`);
    await expect(admin.getByText(childName)).toBeVisible();
    await expect(admin.getByText('Menunggu verifikasi')).toBeVisible();

    // --- The two sessions stay apart
    await applicant.goto(`${centralUrl}/ppdb`);
    await expect(applicant).toHaveURL(`${centralUrl}/login`);

    await admin.goto(`${centralUrl}/calon-siswa`);
    await expect(admin).toHaveURL(`${centralUrl}/calon-siswa/masuk`);

    // --- The applicant signs out and can sign in again
    await applicant.goto(`${centralUrl}/calon-siswa`);
    await applicant.getByRole('button', { name: 'Keluar' }).click();
    await expect(applicant).toHaveURL(`${centralUrl}/calon-siswa/masuk`);

    await applicant.getByLabel('Email').fill(email);
    await applicant.getByLabel('Kata sandi').fill(password);
    await applicant.getByRole('button', { name: 'Masuk' }).click();
    await expect(applicant).toHaveURL(`${centralUrl}/calon-siswa`, {
        timeout: slow,
    });
    await expect(applicant.getByText(childName)).toBeVisible();

    expect(errors).toEqual([]);

    await applicantContext.close();
    await adminContext.close();
});
