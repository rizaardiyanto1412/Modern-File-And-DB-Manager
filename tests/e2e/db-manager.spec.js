const { test, expect } = require('@playwright/test');

const adminUser = process.env.MFM_ADMIN_USER || 'admin';
const adminPass = process.env.MFM_ADMIN_PASS || '';

test.describe('Modern File Manager DB Manager', () => {
  test.skip(!adminPass, 'Set MFM_ADMIN_PASS to run e2e test');

  test('shows db manager page with warning and launch action', async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.getByLabel(/username|email address/i).fill(adminUser);
    await page.getByLabel(/password/i).fill(adminPass);
    await page.getByRole('button', { name: /log in/i }).click();

    await page.goto('/wp-admin/admin.php?page=modern-file-manager-db');
    await expect(page.getByRole('heading', { name: 'DB Manager' })).toBeVisible();
    await expect(page.getByText(/database operations can permanently change or delete data/i)).toBeVisible();
    await expect(page.getByText(/Environment:/)).toBeVisible();
    await expect(page.getByRole('link', { name: 'Open Adminer' })).toBeVisible();
  });

  test('rejects tampered launch token with safe error', async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.getByLabel(/username|email address/i).fill(adminUser);
    await page.getByLabel(/password/i).fill(adminPass);
    await page.getByRole('button', { name: /log in/i }).click();

    await page.goto('/wp-admin/admin.php?page=modern-file-manager-db');

    const launchHref = await page.getByRole('link', { name: 'Open Adminer' }).getAttribute('href');
    expect(launchHref).toBeTruthy();

    const launchUrl = new URL(launchHref, page.url());
    launchUrl.searchParams.set('sig', 'tampered-signature');

    await page.goto(launchUrl.toString());
    await expect(page.getByText(/invalid or expired db manager launch token/i)).toBeVisible();
  });
});
