# Browser Testing Quick Start

## Setup (5 minutes)

### 1. Install dependencies
```bash
npm install  # Install Playwright and dependencies
```

### 2. Configure your Laravel app
In your Laravel application, ensure:
- The Settings model and migrations are installed
- A Livewire component displays settings (`/settings` route)
- Your app is running on `http://localhost:8000`

### 3. Update component test IDs
Add `data-testid` attributes to your Livewire views (see [BROWSER_TESTING.md](BROWSER_TESTING.md) for details).

### 4. Run tests
```bash
# Headless (CI mode)
npm test

# With browser visible
npm run test:headed

# Interactive UI
npm run test:ui
```

## Test Files

- **`tests/Browser/settings-form.spec.ts`** - Tests for the SettingsForm Livewire component
  - Creating, editing, deleting settings
  - Filtering and searching
  - Input type validation
  - Responsive design
  - Accessibility

- **`tests/Browser/settings-page.spec.ts`** - Tests for page structure and UX
  - Loading states
  - Error handling
  - Keyboard navigation
  - Performance metrics
  - Empty states

## Common Commands

```bash
# Run all tests once
npm test

# Watch mode (rerun on file change)
npm test -- --watch

# Run specific test file
npx playwright test tests/Browser/settings-form.spec.ts

# Run specific test by name
npx playwright test -g "should edit a setting"

# Debug mode (step through tests)
npm run test:debug

# Generate test from browser interaction
npm run codegen

# View HTML report
npm run test:report
```

## What Tests Cover

✅ **Functionality**
- Create/read/update/delete operations
- Form validation
- Filter and search
- State persistence

✅ **Usability**
- Keyboard navigation (Tab, Enter, Escape)
- Loading states
- Error messages
- Empty states
- Responsive layouts

✅ **Accessibility**
- ARIA attributes
- Label associations
- Focus management
- Touch target sizes

✅ **Performance**
- Page load time < 3s
- No layout shift
- Smooth interactions

✅ **Browsers**
- Chrome/Chromium
- Firefox
- Safari/WebKit
- Mobile Chrome & Safari

## Troubleshooting

**Tests can't connect to server**
```bash
# Terminal 1: Start Laravel
php artisan serve

# Terminal 2: Run tests
npm test
```

**Elements not found**
1. Check `data-testid` attributes in your Blade template
2. Run inspector: `npm run test:debug`
3. Generate test: `npm run codegen`

**Flaky/intermittent failures**
- Use `page.waitForLoadState('networkidle')` for async operations
- Avoid fixed delays (`page.waitForTimeout()`)
- Use proper wait conditions

## Integration with CI/CD

The `.github/workflows/e2e-tests.yml` file automatically:
- Runs on every push/PR
- Tests multiple browsers
- Uploads failure screenshots
- Reports results

## Next Steps

1. See [BROWSER_TESTING.md](BROWSER_TESTING.md) for full documentation
2. Run `npm run test:ui` for interactive test explorer
3. Run `npm run codegen` to generate tests from your interactions
4. Add tests to CI pipeline

## Resources

- [Playwright Documentation](https://playwright.dev)
- [Testing Best Practices](https://playwright.dev/docs/best-practices)
- [API Reference](https://playwright.dev/docs/api/class-page)

## Support

For issues related to:
- This package: Check [BROWSER_TESTING.md](BROWSER_TESTING.md)
- Playwright: See [playwright.dev](https://playwright.dev)
- Your Laravel app: Check your app's documentation
