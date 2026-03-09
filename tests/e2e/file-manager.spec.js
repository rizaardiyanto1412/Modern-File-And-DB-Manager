const { test, expect } = require('@playwright/test');

const adminUser = process.env.MFM_ADMIN_USER || 'admin';
const adminPass = process.env.MFM_ADMIN_PASS || '';

test.describe('Modern File Manager', () => {
  test.skip(!adminPass, 'Set MFM_ADMIN_PASS to run e2e test');

  test('loads file manager and navigates without full reload', async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.getByLabel(/username|email address/i).fill(adminUser);
    await page.getByLabel(/password/i).fill(adminPass);
    await page.getByRole('button', { name: /log in/i }).click();

    await page.goto('/wp-admin/admin.php?page=modern-file-manager');
    await expect(page.getByRole('heading', { name: 'Modern File Manager' })).toBeVisible();

    const before = page.url();
    await page.getByRole('button', { name: 'Root' }).first().click();
    await expect(page).toHaveURL(/admin\.php\?page=modern-file-manager/);
    await expect(page.locator('.mfm-table')).toBeVisible();
    await expect(page).toHaveURL(before);
  });
});
