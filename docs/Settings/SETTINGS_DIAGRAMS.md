# Settings System — Architecture Diagrams

## 1. System Overview

```
┌─────────────────┐
│  Developer      │
│  Code           │
├─────────────────┤
│ settings('key') │ ◄─────┐
└────────┬────────┘       │
         │                │
         ▼                │
┌──────────────────────┐  │
│ Global Helper        │  │ (returns value immediately)
│ (SettingsHelper)     │  │
└────────┬─────────────┘  │
         │                │
         ▼                │
┌──────────────────────┐  │
│ SettingsService      │  │
│                      │  │
│ • get()              │  │
│ • set()              │  │
│ • loadMerged()       │  │
│ • invalidate()       │  │
└──────┬───────────────┘  │
       │                  │
       ├─────┬────────┐   │
       │     │        │   │
       ▼     ▼        ▼   │
    ┌────┐ ┌─────┐ ┌────┴──────┐
    │Cache: Redis │ │ Database │
    │ (HIT)       │ │ (MISS)   │
    │ <1ms        │ │ Query    │
    └────┬────────┘ └────┬─────┘
         │               │
         └───────┬───────┘
                 │
                 ▼
            ┌──────────┐
            │ Value    │
            │ (Casted) │
            └──────────┘
```

---

## 2. Data Flow: Read Path (Happy Case)

```
Request arrives
       │
       ▼
User calls: settings('email.driver')
       │
       ├─► Global Helper catches it
       │
       ▼
SettingsService::get('email.driver', null, null)
       │
       ├─► Resolve scope → 'global'
       │
       ├─► loadMerged()
       │   ├─► load('global')
       │   │   ├─► Cache::rememberForever('settings:global', ...)
       │   │   │   ├─► [CACHE HIT] Return from Redis (typical case)
       │   │   │   │   Time: <1ms, Queries: 0 ✓
       │   │   │   │
       │   │   │   └─► [CACHE MISS] Query DB (first request)
       │   │   │       Setting::forScope(null)->get()
       │   │   │       Store in Redis
       │   │   │       Time: 50ms, Queries: 1
       │   │   │
       │   │   └─► Return array keyed by 'key'
       │   │
       │   └─► Merge with scoped (if applicable)
       │
       ├─► data_get($merged, 'email.driver', null)
       │   └─► Find nested value via dot notation
       │
       ▼
Return casted value
```

---

## 3. Data Flow: Write Path

```
Admin updates setting
       │
       ▼
Livewire: updateSetting('email.driver', 'smtp')
       │
       ▼
SettingsService::set('email.driver', 'smtp', null)
       │
       ├─► Setting::updateOrCreate([...], ['value' => 'smtp'])
       │   └─► 1 database UPDATE query
       │
       ├─► invalidate(null)  ◄────────────────┐
       │   └─► Cache::forget('settings:global')│
       │                                       │
       ▼                                       │
   Cache INVALIDATED                          │
   Next read will hit DB (cache miss)         │
       │                                      │
       └──► [IMPORTANT: Observer watches too]─┘
           If update via any path, cache
           is automatically invalidated
           
After invalidation:
       │
       ▼
Next request: settings('email.driver')
       │
       ▼
Cache MISS → Query DB → Reload into Redis → Return value
```

---

## 4. Scope Cascade

```
Request with User Scope
       │
       ▼
settings('theme', 'light', auth()->user())
       │
       ├─► loadMerged(User:5)
       │   │
       │   ├─► Global Settings
       │   │   ├─ theme: 'dark'
       │   │   ├─ logo: 'logo.png'
       │   │   └─ ...
       │   │
       │   ├─► User-Specific Settings (override)
       │   │   └─ theme: 'light'
       │   │
       │   └─► Merged Result (user overrides global)
       │       ├─ theme: 'light' ◄─── FROM USER (wins)
       │       ├─ logo: 'logo.png' ◄──── FROM GLOBAL
       │       └─ ...
       │
       ▼
Return 'light'

** Fallback Order:
   1. User-specific (if provided)
   2. Global
   3. Default parameter
```

---

## 5. Multi-Scoped Settings Hierarchy

```
┌────────────────────────────┐
│    Global Settings         │  (All users, default config)
│  • site_name               │
│  • email.driver = 'smtp'   │
│  • theme = 'light'         │
└───────────┬────────────────┘
            │
     ┌──────┴──────┐
     │             │
     ▼             ▼
┌──────────┐  ┌──────────┐
│ Tenant:1 │  │ Tenant:2 │
├──────────┤  ├──────────┤
│ theme=   │  │ theme=   │
│ 'dark'   │  │ 'auto'   │
└────┬─────┘  └────┬─────┘
     │             │
     ├─────┬───┐   │
     │     │   │   │
     ▼     ▼   ▼   ▼
  User:5 User:6  User:7
  theme='dark' theme='dark' theme='custom'
```

**Resolution for User:5 at Tenant:1**:
```
→ Check User:5 (Tenant:1 context) → found 'dark'
→ Return 'dark'

Resolution for User:6 at Tenant:1:
→ Check User:6 → not found
→ Check Tenant:1 → found 'dark'
→ Return 'dark'

Resolution for Unknown setting:
→ Check User → not found
→ Check Tenant → not found
→ Check Global → found
→ Return value
→ If still not found: return default_parameter or null
```

---

## 6. Cache Key Strategy

```
Cache Keys Generated:

Global Settings:
  settings:global

Tenant-Specific:
  settings:Tenant:12
  settings:Tenant:15

User-Specific:
  settings:User:5
  settings:User:123

Organization:
  settings:Organization:7

Each scope has its own cache key:
  • Isolated invalidation (only affected scope clears)
  • No cross-scope contamination
  • Parallel reads from different scopes
```

---

## 7. Observer Pattern (Auto Cache Invalidation)

```
Setting Model Updated
       │
       ├─► Observer::updated() triggers
       │
       ├─► Extract scope from Setting record
       │   └─ scope_type: 'User', scope_id: 5
       │
       ├─► Resolve cache key: 'settings:User:5'
       │
       ├─► SettingsService::invalidate('User:5')
       │   └─► Cache::forget('settings:User:5')
       │
       ▼
   Previous Cache Cleared
   
Next request for that scope:
       └─► Cache MISS → Reload from DB
```

**No manual invalidation needed!** (Observer does it automatically)

---

## 8. Type System Flow

```
Database Value (raw)
       │
       ▼
Setting Model
├─ type: 'boolean'
├─ value: 1 (JSON/int)
└─ getCastedValue()
       │
       ▼
Type Match
├─ 'boolean' → (bool) $value → true ✓
├─ 'number' → (int) $value → 42 ✓
├─ 'decimal' → (float) $value → 3.14 ✓
└─ 'text' → $value (string) ✓
       │
       ▼
Return casted, type-safe value
to application code
```

---

## 9. Admin UI Component Selection

```
Database Setting Row
├─ key: 'email_driver'
├─ type: 'select'
├─ meta.options: ['smtp', 'mailgun', 'log']
└─ value: 'smtp'
       │
       ▼
ComponentRegistry
├─ 'text' → TextInput
├─ 'select' → SelectDropdown
├─ 'boolean' → ToggleSwitch
├─ 'number' → NumberInput
├─ 'json' → JsonEditor
└─ 'file' → FileUploader
       │
       ▼
Lookup: type='select'
Result: SelectDropdown component
       │
       ▼
Livewire renders:
<select wire:change="updateSetting(...)">
  <option value="smtp" selected>SMTP</option>
  <option value="mailgun">Mailgun</option>
  <option value="log">Log</option>
</select>

User changes → new value → updateSetting() → DB → Cache invalidated
```

---

## 10. Performance Comparison

### Before (No Caching)
```
Request 1: settings('key1')
  DB Query 1: SELECT * FROM settings WHERE key='key1'
  Response: 45ms

Request 2: settings('key2')
  DB Query 1: SELECT * FROM settings WHERE key='key2'
  Response: 42ms

Request N: ...
  Time: O(N) → 50ms per request
  CPU: HIGH (repeated queries)
```

### After (This System)
```
Request 1: settings('key1')
  Cache Miss → DB Query 1: SELECT * FROM settings
  Store all in Redis
  Response: 50ms (first request)

Request 2: settings('key2')
  Cache Hit → no query
  Response: <1ms ✓

Request N: ...
  Time: O(1) → <1ms per request
  CPU: MINIMAL (Redis lookup)
  DB: ZERO queries

Improvement: 50x faster after first request
```

---

## 11. Deployment Flow

```
1. Migrate
   ├─ php artisan migrate
   └─ Settings table created

2. Seed
   ├─ php artisan db:seed SettingsSeeder
   └─ Initial settings inserted

3. Warm
   ├─ php artisan settings:warm
   └─ All settings cached to Redis

4. Ready
   ├─ Settings cached
   ├─ Developer API available
   ├─ Admin panel active
   └─ Zero-DB-query application
```

---

## 12. Testing Approach

```
Layer 1: Unit Tests (Service)
├─ get() returns correct value
├─ set() updates value
├─ invalidate() clears cache
├─ Scope resolution works
└─ Type casting correct

Layer 2: Feature Tests (Livewire)
├─ Admin UI loads settings
├─ Update setting via UI
├─ Validation errors
└─ Permissions denied

Layer 3: Performance Tests
├─ Cache hit < 1ms
├─ Cache miss < 50ms
├─ Load 100 settings < 2ms
└─ Memory usage acceptable

Layer 4: Integration Tests
├─ Settings work in middleware
├─ Settings work in jobs
├─ Settings bridge to config()
└─ Multi-tenant isolation holds
```

---

## Quick Reference: State Transitions

```
Setting Lifecycle:
  
  ┌─────────────────┐
  │  CREATED        │ ← Inserted via API/UI
  │  cache MISS     │   First read hits DB
  └────────┬────────┘   Stored in Redis
           │
           ↓ (read)
  ┌─────────────────┐
  │  CACHED         │ ← Subsequent reads hit Redis
  │  cache HIT      │   <1ms response
  └────────┬────────┘
           │
           ↓ (update)
  ┌─────────────────┐
  │  INVALIDATED    │ ← Cache key deleted
  │  cache MISS     │   Next read re-queries DB
  └────────┬────────┘
           │
           ↓ (read)
           ↑ (back to CACHED)
```

---

**Diagrams Created**: 2026-03-25
