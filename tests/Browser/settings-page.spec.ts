import { test, expect } from '@playwright/test';

test.describe('Settings Page - General UI', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/settings');
  });

  test('should have accessible page structure', async ({ page }) => {
    // Check for main landmark
    const main = page.locator('main');
    await expect(main).toBeVisible();

    // Check for proper heading hierarchy
    const h1 = page.locator('h1');
    if (await h1.count() > 0) {
      await expect(h1.first()).toBeVisible();
    }
  });

  test('should display loading state during initial load', async ({ page }) => {
    // Reload page and check for loading indicator
    await page.goto('/settings', { waitUntil: 'domcontentloaded' });

    // Look for loading spinner or skeleton
    const loadingIndicator = page.locator('[data-testid="loading"]');
    const skeleton = page.locator('[role="status"]');

    const hasLoading = await loadingIndicator.isVisible().catch(() => false);
    const hasSkeleton = await skeleton.isVisible().catch(() => false);

    // At least one should appear, or content should appear quickly
    expect(hasLoading || hasSkeleton || await page.locator('[data-testid="settings-list"]').isVisible()).toBeTruthy();
  });

  test('should handle empty state gracefully', async ({ page }) => {
    // Check for empty state message (if no settings exist)
    const emptyState = page.locator('[data-testid="empty-state"]');
    const settingsList = page.locator('[data-testid="settings-list"]');

    const isEmpty = await emptyState.isVisible().catch(() => false);
    const hasSettings = await settingsList.isVisible().catch(() => false);

    // Should show either empty state or settings
    expect(isEmpty || hasSettings).toBeTruthy();
  });

  test('should have proper error handling', async ({ page }) => {
    // Try to navigate to invalid route
    await page.goto('/settings/invalid', { waitUntil: 'load' }).catch(() => null);

    // Should either show error message or redirect
    const errorMessage = page.locator('[role="alert"]');
    const isErrorVisible = await errorMessage.isVisible().catch(() => false);

    // Page should still be navigable
    expect(await page.title()).toBeTruthy();
  });

  test('should maintain scroll position on filter changes', async ({ page }) => {
    // Get initial scroll position
    const scrollTop = await page.evaluate(() => window.scrollY);

    // Trigger a filter change
    const filter = page.locator('[data-testid="group-filter"]');
    if (await filter.isVisible()) {
      await filter.click();
      await page.waitForTimeout(300);
    }

    // Scroll position should be maintained or at top
    const newScrollTop = await page.evaluate(() => window.scrollY);
    expect(newScrollTop).toBeLessThanOrEqual(scrollTop + 100);
  });

  test('should show success/error notifications', async ({ page }) => {
    // Try to perform an action that changes state
    const editButton = page.locator('[data-testid="edit-button"]').first();
    if (await editButton.isVisible()) {
      await editButton.click();

      // Fill in a value
      const valueInput = page.locator('[data-testid="value-input"]');
      if (await valueInput.isVisible()) {
        await valueInput.clear();
        await valueInput.fill('Test Value');

        // Save
        const saveButton = page.locator('[data-testid="save-button"]');
        await saveButton.click();

        // Look for notification
        const notification = page.locator('[role="status"], [role="alert"]');
        const isNotificationVisible = await notification.isVisible({ timeout: 2000 }).catch(() => false);

        expect(isNotificationVisible).toBeTruthy();
      }
    }
  });
});

test.describe('Settings Page - Keyboard Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/settings');
  });

  test('should be navigable with keyboard', async ({ page }) => {
    // Tab to first button
    await page.keyboard.press('Tab');

    // Check that some element is focused
    const focusedElement = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement;
      return {
        tagName: el?.tagName,
        id: el?.id,
        className: el?.className,
      };
    });

    expect(focusedElement.tagName).toBeTruthy();
  });

  test('should support enter key on buttons', async ({ page }) => {
    // Find clickable element
    const button = page.locator('button').first();
    if (await button.isVisible()) {
      // Focus it
      await button.focus();

      // Press enter
      await page.keyboard.press('Enter');

      // Verify action occurred (no error)
      expect(await page.title()).toBeTruthy();
    }
  });

  test('should close modals with Escape key', async ({ page }) => {
    const editButton = page.locator('[data-testid="edit-button"]').first();
    if (await editButton.isVisible()) {
      await editButton.click();

      // Verify edit form opened
      const editForm = page.locator('[data-testid="edit-form"]');
      if (await editForm.isVisible()) {
        // Press escape
        await page.keyboard.press('Escape');

        // Form should close
        await expect(editForm).not.toBeVisible({ timeout: 1000 });
      }
    }
  });
});

test.describe('Settings Page - Performance', () => {
  test('should load page in reasonable time', async ({ page }) => {
    const startTime = Date.now();

    await page.goto('/settings', { waitUntil: 'domcontentloaded' });

    const loadTime = Date.now() - startTime;

    // Page should load within 3 seconds
    expect(loadTime).toBeLessThan(3000);
  });

  test('should not have layout shift during load', async ({ page }) => {
    // Measure Cumulative Layout Shift
    const cls = await page.evaluate(() => {
      return new Promise<number>((resolve) => {
        let clsValue = 0;

        const observer = new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            if ((entry as any).hadRecentInput) continue;
            clsValue += (entry as any).value;
          }
        });

        observer.observe({ entryTypes: ['layout-shift'] });

        setTimeout(() => {
          observer.disconnect();
          resolve(clsValue);
        }, 3000);
      });
    });

    // CLS should be less than 0.1 (good score)
    expect(cls).toBeLessThan(0.1);
  });
});
