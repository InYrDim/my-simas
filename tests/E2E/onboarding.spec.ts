import { expect, test } from '@playwright/test';

import { accounts, centralUrl, consoleUrl } from './support/env';

/**
 * The onboarding hand-over across the two real hosts: the provider
 * approves the seeded application on the console host, then the
 * applicant logs in on the central host with the password they already
 * had and lands in their new school.
 *
 * Uses the pending application PlatformDevSeeder creates, so no mail has
 * to be read. Runs once per seeded database (an application is approved
 * only once) — global-setup reseeds before every run.
 */
test('an approved applicant logs straight into their school', async ({ browser }) => {
    // Three logins across two hosts on a single-threaded server take about
    // 30 seconds — right at the default limit.
    test.setTimeout(90_000);

    // --- Provider, on the console host
    const providerContext = await browser.newContext();
    const provider = await providerContext.newPage();

    await provider.goto(`${consoleUrl}/login`);
    await provider.getByLabel('Email').fill(accounts.provider.email);
    await provider.getByLabel('Kata sandi').fill(accounts.provider.password);
    await provider.getByRole('button', { name: 'Masuk' }).click();
    await expect(provider).toHaveURL(`${consoleUrl}/dashboard`);

    await provider.getByRole('link', { name: 'Pengajuan', exact: true }).click();
    await provider.getByRole('link', { name: new RegExp(accounts.applicant.school) }).click();
    await expect(provider.getByText(accounts.applicant.email)).toBeVisible();

    await provider.getByRole('button', { name: 'Setujui & buat sekolah' }).click();
    await expect(provider).toHaveURL(`${consoleUrl}/applications`);
    await expect(provider.getByText('Aplikasi disetujui')).toBeVisible();

    await providerContext.close();

    // --- Applicant, on the central host, in a browser of their own
    const applicantContext = await browser.newContext();
    const applicant = await applicantContext.newPage();

    await applicant.goto(`${centralUrl}/pemohon/masuk`);
    await applicant.getByLabel('Email').fill(accounts.applicant.email);
    await applicant.getByLabel('Kata sandi').fill(accounts.applicant.password);
    await applicant.getByRole('button', { name: 'Masuk' }).click();

    // The applicant login opened the SCHOOL session.
    await expect(applicant).toHaveURL(`${centralUrl}/beranda`);
    await expect(applicant.getByText(accounts.applicant.school).first()).toBeVisible();

    // A school page that needs the session and its permissions.
    await applicant.goto(`${centralUrl}/master/sekolah`);
    await expect(applicant.getByRole('heading', { name: 'Profil Sekolah' })).toBeVisible();

    // The provider console stays closed to a school session.
    await applicant.goto(`${consoleUrl}/dashboard`);
    await expect(applicant).toHaveURL(`${consoleUrl}/login`);

    await applicantContext.close();
});
