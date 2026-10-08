import { expect, test } from '@playwright/test';

import { consoleUrl } from './support/env';

/**
 * The three front doors, each on the host it really lives on, rendering
 * without a JavaScript error.
 */
test.describe('front doors', () => {
    let errors: string[];

    test.beforeEach(({ page }) => {
        errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
    });

    test.afterEach(() => {
        expect(errors).toEqual([]);
    });

    test('landing page, then school login, on the central host', async ({
        page,
    }) => {
        await page.goto('/');

        await expect(
            page.getByRole('heading', {
                level: 1,
                name: 'Administrasi sekolah, diisi sekali dan benar.',
            }),
        ).toBeVisible();

        await page
            .getByRole('navigation', { name: 'Utama' })
            .getByRole('link', { name: 'Masuk', exact: true })
            .click();

        await expect(page).toHaveURL(/\/login$/);
        await expect(
            page.getByRole('heading', { name: 'Masuk ke akun Anda' }),
        ).toBeVisible();
        await expect(page.getByLabel('Kode sekolah')).toBeVisible();
    });

    test('applicant registration on the central host', async ({ page }) => {
        await page.goto('/login');
        await page.getByRole('link', { name: 'Daftarkan sekolah' }).click();

        await expect(page).toHaveURL(/\/daftar-sekolah$/);
        await expect(page.getByText('Daftarkan sekolah Anda')).toBeVisible();
    });

    test('provider login on the console host', async ({ page }) => {
        await page.goto(`${consoleUrl}/`);

        await expect(page).toHaveURL(`${consoleUrl}/login`);
        await expect(page.getByText('Console Provider')).toBeVisible();
        // The console never offers the school code field.
        await expect(page.getByLabel('Kode sekolah')).toHaveCount(0);
    });
});
