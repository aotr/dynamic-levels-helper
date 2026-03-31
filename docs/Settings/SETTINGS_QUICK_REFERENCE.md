# Advanced Settings System — Quick Reference

## What You're Building

A **database-driven, cached, multi-scoped settings system** that replaces hardcoded config. Think of it as "Voyager on steroids" — dynamic, performant, and developer-friendly.

---

## One-Sentence Summary per Phase

| Phase | Goal |
|-------|------|
| **Phase 1** | Schema + Service (core foundation) |
| **Phase 2** | Caching + performance (zero DB queries) |
| **Phase 3** | Developer API (`settings()` helper) |
| **Phase 4** | Admin UI (Livewire form builder) |
| **Phase 5** | Testing (unit + feature + perf tests) |

---

## Key Numbers

| Metric | Target | Notes |
|--------|--------|-------|
| **Queries per read** | 0 (cached) | 1 on cold cache |
| **Response time** | <1ms | With cache hit |
| **Cache invalidation** | Instant | On write via Observer |
| **Test coverage** | >90% | Unit + Feature |

---

## Core Concepts

### 1. **Scope** → Multi-tenancy support
```
global settings (app-wide)
  ↓ merged with
user/tenant settings (specific override)
```

### 2. **Type System** → Automatic UI + casting
```
type: "select" 
  → render dropdown component
  → cast value to correct type
```

### 3. **Cache Forever** → No DB hits after warmup
```
First request: Query DB → Store in Redis forever
Subsequent: Read from Redis → O(1) access
Update: DB update → Invalidate cache immediately
```

### 4. **Dot Notation** → Nested access
```
settings('email.driver')  // drill into JSON
settings('features.chat.enabled')  // nested
```

---

## Storage Options & Fallback Chain

| Priority | Source | Use Case | Notes |
|----------|--------|----------|-------|
| 1st | Database (Cached) | Admin changes, per-user overrides | Fastest (< 1ms) |
| 2nd | Settings File | Version-controlled configs | config/dynamic-settings.json |
| 3rd | Environment Vars | Deploy-time configs | .env or CI/CD secrets |
| 4th | SQLite Fallback | DB unavailable | Automatic failover |
| 5th | Default Value | Code-defined fallback | Hardcoded in service |

### Fallback Chain in Action
```
settings('email.driver')
  → Cache hit? Return cached value
  → DB down? Try SQLite fallback
  → No fallback? Load from config file
  → No file? Check env SETTING_EMAIL_DRIVER
  → Not found? Return 'mailgun' (default)
```

---

## Critical Design Decisions

### ✅ Decision 1: JSON Columns (NOT Serialized Text)
**Why?** DB-level query support, cleaner migrations, better for complex configs.

### ✅ Decision 2: Polymorphic Scope (NOT Separate Tables)
**Why?** One flexible table vs. rigid `user_settings`, `tenant_settings`, `org_settings`.

### ✅ Decision 3: Cache Forever (NOT TTL)
**Why?** Zero stale data, predictable behavior, invalidate explicitly on writes.

### ✅ Decision 4: Observer Pattern (NOT Manual Invalidation)
**Why?** Automatic cache busting, no forgotten invalidation calls.

### ✅ Decision 5: Multi-Source Fallback (DB → File → Env → SQLite)
**Why?** Works offline, respects env separation, handles DB downtime gracefully.

---

## The 5 Files That Matter Most

| File | Purpose | Complexity |
|------|---------|-----------|
| Migration | Schema definition | Low |
| Setting Model | Queries + scopes | Medium |
| SettingsService | Business logic + cache | **High** |
| Global Helper | Developer ergonomics | Low |
| Livewire Component | Admin UI | Medium |

**👉 Start with Migration → Model → Service**

---

## Usage Examples (What Developers Will Write)

```php
// Blade
<h1>{{ settings('site_name') }}</h1>

// Controller
$driver = settings('email.driver');

// Middleware
if (!settings('features.api.enabled', false)) {
    abort(403);
}

// Job
Mail::driver(settings('email.driver'))->send(...);

// Config bridge
config(['mail.from' => settings('email.from')]);

// Scoped (user-specific)
settings('theme', 'light', auth()->user());
```

---

## Testing Pyramid

```
                 /\
               /    \
             / E2E   \          1 test (Cypress/Playwright)
           /____________\
         /              \
       / Feature Tests  \    5-10 tests (Livewire, API)
      /________________\
     /                  \
   / Unit Tests        \   15-20 tests (Service, Model)
  /____________________\
```

---

## Database Schema at a Glance

```
settings
├── id (primary)
├── key (string, unique per scope)
├── group (general, email, features, etc.)
├── display_name ("Email Driver")
├── value (JSON: {"type": "smtp"})
├── meta (JSON: validation, options, UI hints)
├── type (text, select, boolean, json, file)
├── scope (polymorphic: User:5, Tenant:12, null=global)
└── timestamps
```

---

## Implementation Sequence (Do This Order)

```
Day 1:
  1. Create migration
  2. Create Setting model + scopes
  3. Create SettingsService (no cache first)
  4. Register in provider
  
Day 2:
  5. Add caching to SettingsService
  6. Create Observer for cache invalidation
  7. Global helper function
  
Day 3:
  8. Config bridge (SettingsConfigProvider)
  9. Livewire component (basic form)
  10. Blade components (text-input, select, etc.)
  
Day 4:
  11. Unit tests
  12. Feature tests
  13. Performance testing
  
Day 5+:
  14. Documentation
  15. Code review
  16. Deployment
```

---

## Deployment Checklist

```
□ Migration: php artisan migrate
□ Seed defaults: php artisan db:seed SettingsSeeder
□ Warm cache: php artisan settings:warm
□ Register provider in config/app.php
□ Test locally: php artisan test
□ Code format: vendor/bin/pint
□ Deploy to staging
□ Monitor logs
□ Deploy to production
```

---

## Red Flags to Watch

| ⚠️ Issue | Fix |
|---------|-----|
| Duplicate cache keys | Use consistent `resolveCacheKey()` logic |
| Cache not invalidating | Ensure Observer is registered |
| N+1 queries in admin UI | Use `with()` eager load or built-in cache |
| Settings lost after deploy | Run `php artisan settings:warm` in deployment script |
| Slow first load | Implement lazy-load per scope instead of loading all |

---

## Common Questions (FAQ)

### Q: Can I have settings per-user?
**A:** Yes! Scope can be `User:5`. Cascades: user → global.

### Q: What if I want to encrypt sensitive settings?
**A:** Use `encrypted` attribute or custom cast on value.

### Q: Can I validate settings before save?
**A:** Yes! Use `meta.validation` rules, checked in Livewire component.

### Q: What's the deal with `meta` field?
**A:** It holds UI metadata: validation rules, dropdown options, placeholder text. DB-driven form rendering.

### Q: How do I add a new setting at runtime?
**A:** Create via Livewire admin UI or:
```php
Setting::create([
    'key' => 'new_setting',
    'display_name' => 'New Setting',
    'value' => 'default',
    'type' => 'text',
    'meta' => ['validation' => 'required|string'],
]);
```

### Q: What if Redis goes down?
**A:** Falls back to DB queries (slower but functional). No data loss.

---

## Performance Comparison

### Before (Voyager-style + Env)
```
Env vars     → only at deploy time
DB queries   → every request (no cache)
Config       → hardcoded in settings table
Result       → Slow, rigid, redeploy to change
```

### After (This System)
```
DB settings  → last-deploys config
Redis cache  → zero queries (hot path)
Dynamic UI   → forms generated from schema
Result       → Fast (sub-1ms), flexible, live changes
```

---

## File Locations (Quick Reference)

```
Migration:           database/migrations/2026_03_25_000000_create_settings_table.php
Model:               app/Models/Setting.php
Service:             app/Services/SettingsService.php
Observer:            app/Observers/SettingObserver.php
Command:             app/Console/Commands/WarmSettingsCache.php
Helper:              app/Helpers/SettingsHelper.php
Config Provider:     app/Providers/SettingsConfigProvider.php
Livewire Component:  app/Http/Livewire/Admin/SettingsManager.php
Blade Templates:     resources/views/components/settings/*.blade.php
Tests:               tests/Unit/Services/SettingsServiceTest.php
                     tests/Feature/Admin/SettingsManagerTest.php
```

---

## Next Steps

1. **Read** the full `SETTINGS_ARCHITECTURE_PLAN.md` (comprehensive)
2. **Review** the schema and database decisions with your team
3. **Start** with Phase 1 (migration + model + service)
4. **Test** with simple `settings('key')` access
5. **Iterate** through phases 2-5

---

**Document Created**: 2026-03-25  
**Last Updated**: 2026-03-25
