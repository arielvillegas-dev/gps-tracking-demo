import { expect, test } from '@playwright/test';

test('renders interactive map trace, supports replay step controls, and ingests live GPS ping', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('h1')).toContainText('GPS Tracking & Route Visualization Demo');

  await expect(page.locator('#vector-canvas polyline')).toHaveCount(2);
  await expect(page.locator('#vector-canvas circle')).toHaveCount(5);

  await page.click('#btn-reset');
  await expect(page.locator('#vector-canvas circle')).toHaveCount(1);

  await page.click('#btn-step');
  await expect(page.locator('#vector-canvas circle')).toHaveCount(2);

  await page.click('#btn-live-ping');
  await expect(page.locator('#kpi-pings')).toHaveText('6');
  await expect(page.locator('#vector-canvas circle')).toHaveCount(6);
});
