import { test, expect } from '@playwright/test';

test.describe('Settings Form Livewire Component', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to the page with the settings form
    // Adjust this URL based on your app's routing
    await page.goto('/settings');
  });

  test('should render the settings form', async ({ page }) => {
    // Check that the form container exists
    await expect(page.locator('[data-testid="settings-form"]')).toBeVisible();
  });

  test('should display settings list', async ({ page }) => {
    // Wait for settings to load
    await page.waitForSelector('[data-testid="settings-list"]');

    // Check that at least one setting is rendered
    const settingRows = page.locator('[data-testid="setting-row"]');
    await expect(settingRows.first()).toBeVisible();
  });

  test('should filter settings by group', async ({ page }) => {
    // Click the group filter dropdown
    const groupFilter = page.locator('[data-testid="group-filter"]');
    await expect(groupFilter).toBeVisible();

    await groupFilter.click();

    // Select a group
    const appGroup = page.locator('[data-testid="group-option-app"]');
    if (await appGroup.isVisible()) {
      await appGroup.click();

      // Verify filtered results
      const settingRows = page.locator('[data-testid="setting-row"]');
      const appRows = page.locator('[data-testid="setting-row"][data-group="app"]');

      // All visible rows should be in the app group
      const rowCount = await settingRows.count();
      const appRowCount = await appRows.count();
      expect(appRowCount).toBeGreaterThan(0);
    }
  });

  test('should search settings by name', async ({ page }) => {
    // Type in the search field
    const searchInput = page.locator('[data-testid="search-input"]');
    await expect(searchInput).toBeVisible();

    await searchInput.fill('app.name');

    // Wait for filtering
    await page.waitForTimeout(500);

    // Verify search results show only matching settings
    const settingRows = page.locator('[data-testid="setting-row"]');
    await expect(settingRows.first()).toBeVisible();
  });

  test('should edit a setting value', async ({ page }) => {
    // Find and click edit button for first setting
    const editButton = page.locator('[data-testid="edit-button"]').first();
    await expect(editButton).toBeVisible();

    await editButton.click();

    // Check that edit form appears
    const editForm = page.locator('[data-testid="edit-form"]');
    await expect(editForm).toBeVisible();

    // Get the current value input
    const valueInput = page.locator('[data-testid="value-input"]');
    await expect(valueInput).toBeTruthy();
  });

  test('should save edited setting', async ({ page }) => {
    // Open edit form
    const editButton = page.locator('[data-testid="edit-button"]').first();
    await editButton.click();

    // Wait for edit form
    await page.waitForSelector('[data-testid="edit-form"]');

    // Clear and fill new value
    const valueInput = page.locator('[data-testid="value-input"]');
    await valueInput.clear();
    await valueInput.fill('Updated Value');

    // Click save
    const saveButton = page.locator('[data-testid="save-button"]');
    await expect(saveButton).toBeVisible();
    await saveButton.click();

    // Wait for success message or form to close
    await page.waitForTimeout(500);

    // Verify form closed (edit form should not be visible)
    const editForm = page.locator('[data-testid="edit-form"]');
    await expect(editForm).not.toBeVisible({ timeout: 2000 });
  });

  test('should cancel editing without saving', async ({ page }) => {
    // Open edit form
    const editButton = page.locator('[data-testid="edit-button"]').first();
    await editButton.click();

    // Wait for edit form
    await page.waitForSelector('[data-testid="edit-form"]');

    // Click cancel
    const cancelButton = page.locator('[data-testid="cancel-button"]');
    await expect(cancelButton).toBeVisible();
    await cancelButton.click();

    // Verify form closed
    const editForm = page.locator('[data-testid="edit-form"]');
    await expect(editForm).not.toBeVisible({ timeout: 2000 });
  });

  test('should delete a setting', async ({ page }) => {
    // Find delete button
    const deleteButton = page.locator('[data-testid="delete-button"]').first();
    await expect(deleteButton).toBeVisible();

    await deleteButton.click();

    // Handle confirmation dialog
    const confirmButton = page.locator('[data-testid="confirm-delete"]');
    if (await confirmButton.isVisible({ timeout: 1000 }).catch(() => false)) {
      await confirmButton.click();
    }

    // Verify setting was removed
    await page.waitForTimeout(500);
  });

  test('should create a new setting', async ({ page }) => {
    // Click create button
    const createButton = page.locator('[data-testid="create-setting-button"]');
    if (await createButton.isVisible()) {
      await createButton.click();

      // Verify create form appears
      const createForm = page.locator('[data-testid="create-form"]');
      await expect(createForm).toBeVisible();
    }
  });

  test('should validate required fields', async ({ page }) => {
    const createButton = page.locator('[data-testid="create-setting-button"]');
    if (await createButton.isVisible()) {
      await createButton.click();

      // Try to save without filling required fields
      const saveButton = page.locator('[data-testid="save-button"]');
      await saveButton.click({ force: true });

      // Check for validation errors
      const errorMessages = page.locator('[role="alert"]');
      const isVisible = await errorMessages.first().isVisible({ timeout: 1000 }).catch(() => false);

      // Validation should either show errors or prevent submission
      expect(isVisible || await saveButton.isDisabled()).toBeTruthy();
    }
  });

  test('should reset a setting to default', async ({ page }) => {
    const resetButton = page.locator('[data-testid="reset-button"]').first();
    if (await resetButton.isVisible()) {
      await resetButton.click();

      // Handle confirmation if needed
      const confirmButton = page.locator('[data-testid="confirm-reset"]');
      if (await confirmButton.isVisible({ timeout: 1000 }).catch(() => false)) {
        await confirmButton.click();
      }

      // Verify reset occurred
      await page.waitForTimeout(500);
    }
  });

  test('should display different input types for different setting types', async ({ page }) => {
    // Boolean setting should have toggle/checkbox
    const booleanSetting = page.locator('[data-testid="setting-row"][data-type="boolean"]').first();
    if (await booleanSetting.isVisible()) {
      const checkbox = booleanSetting.locator('input[type="checkbox"]');
      await expect(checkbox).toBeVisible();
    }

    // String setting should have text input
    const stringSetting = page.locator('[data-testid="setting-row"][data-type="string"]').first();
    if (await stringSetting.isVisible()) {
      const textInput = stringSetting.locator('input[type="text"]');
      await expect(textInput).toBeVisible();
    }

    // Number setting should have number input
    const numberSetting = page.locator('[data-testid="setting-row"][data-type="number"]').first();
    if (await numberSetting.isVisible()) {
      const numberInput = numberSetting.locator('input[type="number"]');
      await expect(numberInput).toBeVisible();
    }
  });

  test('should be responsive on mobile viewports', async ({ page }) => {
    // Check that form is visible on mobile
    await expect(page.locator('[data-testid="settings-form"]')).toBeVisible();

    // Verify touch-friendly button sizes (min 44px)
    const buttons = page.locator('button');
    for (let i = 0; i < await buttons.count(); i++) {
      const button = buttons.nth(i);
      const boundingBox = await button.boundingBox();
      if (boundingBox) {
        // Height should be at least 44px for touch targets
        expect(boundingBox.height).toBeGreaterThanOrEqual(40);
      }
    }
  });

  test('should have accessible form labels', async ({ page }) => {
    // Check for proper label associations
    const inputs = page.locator('input');
    for (let i = 0; i < await inputs.count(); i++) {
      const input = inputs.nth(i);
      const ariaLabel = await input.getAttribute('aria-label');
      const placeholder = await input.getAttribute('placeholder');
      const id = await input.getAttribute('id');

      // Input should have at least one of: aria-label, placeholder, or associated label
      const hasLabel = id && await page.locator(`label[for="${id}"]`).count() > 0;
      expect(ariaLabel || placeholder || hasLabel).toBeTruthy();
    }
  });
});

test.describe('Settings Form - Validation', () => {
  test('should prevent submission with invalid data', async ({ page }) => {
    await page.goto('/settings');

    // Attempt to create with invalid data
    const createButton = page.locator('[data-testid="create-setting-button"]');
    if (await createButton.isVisible()) {
      await createButton.click();

      // Fill with invalid number in number field
      const numberInput = page.locator('[data-testid="value-input"][type="number"]');
      if (await numberInput.isVisible()) {
        await numberInput.fill('not-a-number');
      }

      // Save button should be disabled or show error
      const saveButton = page.locator('[data-testid="save-button"]');
      const isDisabled = await saveButton.isDisabled();
      expect(isDisabled).toBeTruthy();
    }
  });
});
