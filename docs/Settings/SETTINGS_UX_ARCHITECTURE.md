# Settings Management UI — UX Architecture & Design System

**Purpose**: Establish comprehensive UX foundation for Settings Manager — CSS architecture, layout framework, component hierarchy, and developer implementation guide.

**Target Users**: Developers managing application settings via browser UI  
**Device Strategy**: Mobile-first, responsive to all screen sizes  
**Accessibility**: WCAG 2.1 AA compliance minimum  

---

## 🎨 CSS Design System Architecture

### Design System Variables

**File**: `css/design-system.css`

```css
:root {
  /* ============================================
     SEMANTIC COLOR PALETTE
     ============================================ */
  
  /* Light Theme Colors */
  --bg-primary: #ffffff;
  --bg-secondary: #f9fafb;
  --bg-tertiary: #f3f4f6;
  --text-primary: #111827;
  --text-secondary: #6b7280;
  --text-muted: #9ca3af;
  --border-color: #e5e7eb;
  --border-light: #f3f4f6;
  
  /* Brand & Semantic Colors */
  --color-primary: #2563eb;      /* Blue 600 - Primary actions */
  --color-primary-light: #3b82f6;  /* Blue 500 - Hover states */
  --color-primary-dark: #1d4ed8;   /* Blue 700 - Active states */
  
  --color-success: #10b981;      /* Green 500 - Success states */
  --color-success-light: #6ee7b7; /* Green 400 - Light backgrounds */
  
  --color-warning: #f59e0b;      /* Amber 500 - Warning states */
  --color-warning-light: #fcd34d; /* Amber 300 - Light backgrounds */
  
  --color-error: #ef4444;        /* Red 500 - Error/Delete states */
  --color-error-light: #fca5a5;   /* Red 300 - Light backgrounds */
  
  --color-info: #0ea5e9;         /* Sky 500 - Info states */
  --color-info-light: #7ee8fa;   /* Sky 300 - Light backgrounds */
  
  /* Neutral Scale for UI */
  --color-gray-50: #f9fafb;
  --color-gray-100: #f3f4f6;
  --color-gray-200: #e5e7eb;
  --color-gray-300: #d1d5db;
  --color-gray-400: #9ca3af;
  --color-gray-500: #6b7280;
  --color-gray-600: #4b5563;
  --color-gray-700: #374151;
  --color-gray-800: #1f2937;
  --color-gray-900: #111827;
  
  /* ============================================
     ELEVATION & SHADOW SYSTEM
     ============================================ */
  
  --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  
  /* ============================================
     TYPOGRAPHY SCALE
     ============================================ */
  
  --text-xs: 0.75rem;     /* 12px */
  --text-sm: 0.875rem;    /* 14px */
  --text-base: 1rem;      /* 16px */
  --text-lg: 1.125rem;    /* 18px */
  --text-xl: 1.25rem;     /* 20px */
  --text-2xl: 1.5rem;     /* 24px */
  --text-3xl: 1.875rem;   /* 30px */
  
  --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  --font-mono: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', 'source-code-pro', monospace;
  
  --font-weight-regular: 400;
  --font-weight-medium: 500;
  --font-weight-semibold: 600;
  --font-weight-bold: 700;
  
  --line-height-tight: 1.2;
  --line-height-normal: 1.5;
  --line-height-relaxed: 1.75;
  
  /* ============================================
     SPACING SYSTEM (4px base grid)
     ============================================ */
  
  --space-1: 0.25rem;    /* 4px */
  --space-2: 0.5rem;     /* 8px */
  --space-3: 0.75rem;    /* 12px */
  --space-4: 1rem;       /* 16px */
  --space-6: 1.5rem;     /* 24px */
  --space-8: 2rem;       /* 32px */
  --space-12: 3rem;      /* 48px */
  --space-16: 4rem;      /* 64px */
  
  /* ============================================
     RESPONSIVE CONTAINER SYSTEM
     ============================================ */
  
  --container-sm: 640px;
  --container-md: 768px;
  --container-lg: 1024px;
  --container-xl: 1280px;
  --container-max: 1400px;
  
  /* ============================================
     BORDER RADIUS SYSTEM
     ============================================ */
  
  --radius-sm: 0.375rem;  /* 6px - Small buttons, badges */
  --radius-md: 0.5rem;    /* 8px - Standard components */
  --radius-lg: 0.75rem;   /* 12px - Cards, modals */
  --radius-xl: 1rem;      /* 16px - Large containers */
  --radius-full: 9999px;  /* Pills, avatars */
  
  /* ============================================
     TRANSITION & ANIMATION
     ============================================ */
  
  --transition-fast: 150ms ease-in-out;
  --transition-base: 200ms ease-in-out;
  --transition-slow: 300ms ease-in-out;
  
  /* ============================================
     Z-INDEX SCALE
     ============================================ */
  
  --z-base: 0;
  --z-dropdown: 100;
  --z-sticky: 200;
  --z-fixed: 300;
  --z-modal-backdrop: 400;
  --z-modal: 500;
  --z-tooltip: 600;
  --z-notification: 700;
}

/* =============================================
   DARK THEME OVERRIDE
   ============================================= */

[data-theme="dark"] {
  --bg-primary: #111827;
  --bg-secondary: #1f2937;
  --bg-tertiary: #374151;
  --text-primary: #f9fafb;
  --text-secondary: #d1d5db;
  --text-muted: #9ca3af;
  --border-color: #374151;
  --border-light: #1f2937;
}

/* =============================================
   SYSTEM THEME PREFERENCE (respects OS dark mode)
   ============================================= */

@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    --bg-primary: #111827;
    --bg-secondary: #1f2937;
    --bg-tertiary: #374151;
    --text-primary: #f9fafb;
    --text-secondary: #d1d5db;
    --text-muted: #9ca3af;
    --border-color: #374151;
    --border-light: #1f2937;
  }
}

/* =============================================
   ACCESSIBILITY: PREFERS REDUCED MOTION
   ============================================= */

@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
```

---

### Typography Foundation

**File**: `css/typography.css`

```css
/* =============================================
   BASE TYPOGRAPHY
   ============================================= */

body {
  font-family: var(--font-sans);
  font-size: var(--text-base);
  line-height: var(--line-height-normal);
  color: var(--text-primary);
  background-color: var(--bg-primary);
  transition: background-color var(--transition-base),
              color var(--transition-base);
}

/* Page Heading - H1 */
.text-heading-1,
h1 {
  font-size: var(--text-3xl);
  font-weight: var(--font-weight-bold);
  line-height: var(--line-height-tight);
  margin-bottom: var(--space-6);
  color: var(--text-primary);
}

/* Section Heading - H2 */
.text-heading-2,
h2 {
  font-size: var(--text-2xl);
  font-weight: var(--font-weight-bold);
  line-height: var(--line-height-tight);
  margin-bottom: var(--space-4);
  color: var(--text-primary);
}

/* Subsection Heading - H3 */
.text-heading-3,
h3 {
  font-size: var(--text-lg);
  font-weight: var(--font-weight-semibold);
  line-height: var(--line-height-tight);
  margin-bottom: var(--space-3);
  color: var(--text-primary);
}

/* Label Text - Inputs, Sections */
.text-label,
label {
  font-size: var(--text-sm);
  font-weight: var(--font-weight-medium);
  color: var(--text-secondary);
  display: block;
  margin-bottom: var(--space-2);
}

/* Body Text - Standard paragraphs */
.text-body,
p {
  font-size: var(--text-base);
  line-height: var(--line-height-normal);
  color: var(--text-primary);
}

/* Secondary Body - Secondary information */
.text-body-secondary {
  font-size: var(--text-base);
  line-height: var(--line-height-normal);
  color: var(--text-secondary);
}

/* Muted Text - Helpful hints, timestamps */
.text-muted {
  font-size: var(--text-sm);
  color: var(--text-muted);
}

/* Code/Monospace */
.text-code,
code {
  font-family: var(--font-mono);
  font-size: var(--text-sm);
  background-color: var(--bg-tertiary);
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-sm);
  color: var(--text-primary);
}

/* Links */
a {
  color: var(--color-primary);
  text-decoration: none;
  transition: color var(--transition-fast);
}

a:hover {
  color: var(--color-primary-light);
  text-decoration: underline;
}

a:active {
  color: var(--color-primary-dark);
}
```

---

### Layout Framework

**File**: `css/layout.css`

```css
/* =============================================
   CONTAINER SYSTEM
   ============================================= */

/* Responsive container with max-width constraints */
.container {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
  padding-left: var(--space-4);
  padding-right: var(--space-4);
}

@media (min-width: 640px) {
  .container {
    max-width: var(--container-sm);
  }
}

@media (min-width: 768px) {
  .container {
    max-width: var(--container-md);
    padding-left: var(--space-6);
    padding-right: var(--space-6);
  }
}

@media (min-width: 1024px) {
  .container {
    max-width: var(--container-lg);
  }
}

@media (min-width: 1280px) {
  .container {
    max-width: var(--container-xl);
  }
}

/* Full-width container for full-bleed designs */
.container-full {
  width: 100%;
  padding-left: var(--space-4);
  padding-right: var(--space-4);
}

@media (min-width: 768px) {
  .container-full {
    padding-left: var(--space-6);
    padding-right: var(--space-6);
  }
}

/* =============================================
   GRID PATTERNS
   ============================================= */

/* 2-column layout, responsive */
.grid-2-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-8);
}

@media (max-width: 768px) {
  .grid-2-col {
    grid-template-columns: 1fr;
    gap: var(--space-6);
  }
}

/* 3-column layout, responsive */
.grid-3-col {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-8);
}

@media (max-width: 1024px) {
  .grid-3-col {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .grid-3-col {
    grid-template-columns: 1fr;
  }
}

/* Auto-fit card grid with minimum column width */
.grid-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: var(--space-6);
}

/* =============================================
   FLEXBOX UTILITIES
   ============================================= */

/* Flex row with space-between (header-footer patterns) */
.flex-row {
  display: flex;
  flex-direction: row;
  gap: var(--space-4);
}

/* Flex row centered on cross-axis */
.flex-row-center {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: var(--space-4);
}

/* Flex between (justify-content: space-between) */
.flex-between {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* Flex column centered */
.flex-column {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

/* =============================================
   SPACING UTILITIES
   ============================================= */

/* Padding utilities */
.p-4 { padding: var(--space-4); }
.p-6 { padding: var(--space-6); }
.p-8 { padding: var(--space-8); }

.px-4 { padding-left: var(--space-4); padding-right: var(--space-4); }
.px-6 { padding-left: var(--space-6); padding-right: var(--space-6); }

.py-4 { padding-top: var(--space-4); padding-bottom: var(--space-4); }
.py-6 { padding-top: var(--space-6); padding-bottom: var(--space-6); }

/* Margin utilities */
.mb-4 { margin-bottom: var(--space-4); }
.mb-6 { margin-bottom: var(--space-6); }
.mb-8 { margin-bottom: var(--space-8); }

.mt-4 { margin-top: var(--space-4); }
.mt-6 { margin-top: var(--space-6); }

.gap-4 { gap: var(--space-4); }
.gap-6 { gap: var(--space-6); }
.gap-8 { gap: var(--space-8); }
```

---

## 🏗️ Component Architecture & Patterns

### Button Component System

**File**: `css/components/buttons.css`

```css
/* =============================================
   BUTTON BASE STYLES
   ============================================= */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-4);
  font-size: var(--text-sm);
  font-weight: var(--font-weight-medium);
  line-height: var(--line-height-normal);
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-fast);
  white-space: nowrap;
  user-select: none;
  text-decoration: none;
  background-color: transparent;
  color: var(--text-primary);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

/* ============================================
   BUTTON VARIANTS
   ============================================ */

/* Primary Button - Primary actions */
.btn-primary {
  background-color: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.btn-primary:hover:not(:disabled) {
  background-color: var(--color-primary-light);
  border-color: var(--color-primary-light);
}

.btn-primary:active:not(:disabled) {
  background-color: var(--color-primary-dark);
  border-color: var(--color-primary-dark);
}

/* Secondary Button - Secondary actions */
.btn-secondary {
  background-color: var(--bg-secondary);
  color: var(--text-primary);
  border-color: var(--border-color);
}

.btn-secondary:hover:not(:disabled) {
  background-color: var(--bg-tertiary);
  border-color: var(--text-secondary);
}

/* Danger Button - Delete/destructive actions */
.btn-danger {
  background-color: var(--color-error);
  color: white;
  border-color: var(--color-error);
}

.btn-danger:hover:not(:disabled) {
  background-color: #dc2626;
  border-color: #dc2626;
}

/* Success Button - Confirm actions */
.btn-success {
  background-color: var(--color-success);
  color: white;
  border-color: var(--color-success);
}

.btn-success:hover:not(:disabled) {
  background-color: #059669;
  border-color: #059669;
}

/* Ghost Button - Subtle/text action */
.btn-ghost {
  background-color: transparent;
  color: var(--color-primary);
  border-color: transparent;
}

.btn-ghost:hover:not(:disabled) {
  background-color: var(--color-primary-light);
  color: white;
}

/* ============================================
   BUTTON SIZES
   ============================================ */

.btn-sm {
  padding: var(--space-1) var(--space-3);
  font-size: var(--text-xs);
}

.btn-md {
  padding: var(--space-2) var(--space-4);
}

.btn-lg {
  padding: var(--space-3) var(--space-6);
  font-size: var(--text-base);
}

/* ============================================
   BUTTON WIDTH
   ============================================ */

.btn-full {
  width: 100%;
}

.btn-block {
  display: flex;
  width: 100%;
}
```

---

### Form Components

**File**: `css/components/forms.css`

```css
/* =============================================
   FORM STRUCTURE
   ============================================= */

.form-group {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin-bottom: var(--space-6);
}

.form-label {
  font-size: var(--text-sm);
  font-weight: var(--font-weight-medium);
  color: var(--text-secondary);
}

.form-label.required::after {
  content: ' *';
  color: var(--color-error);
}

/* =============================================
   FORM INPUTS
   ============================================= */

.form-input,
input[type="text"],
input[type="email"],
input[type="password"],
input[type="number"],
textarea,
select {
  width: 100%;
  padding: var(--space-2) var(--space-3);
  font-family: var(--font-sans);
  font-size: var(--text-base);
  line-height: var(--line-height-normal);
  color: var(--text-primary);
  background-color: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  transition: all var(--transition-fast);
}

.form-input:focus,
input:focus,
textarea:focus,
select:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  background-color: var(--bg-primary);
}

.form-input:disabled,
input:disabled,
textarea:disabled,
select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Textarea - Multi-line input */
textarea {
  resize: vertical;
  min-height: 100px;
}

/* Select - Dropdown */
select {
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234b5563' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right var(--space-3) center;
  padding-right: var(--space-8);
}

/* =============================================
   FORM STATES
   ============================================= */

.form-input.error,
input.error,
textarea.error,
select.error {
  border-color: var(--color-error);
  background-color: rgba(239, 68, 68, 0.02);
}

.form-input.error:focus,
input.error:focus,
textarea.error:focus,
select.error:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.form-input.success,
input.success,
textarea.success {
  border-color: var(--color-success);
}

.form-input.success:focus,
input.success:focus,
textarea.success:focus {
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

/* =============================================
   FORM FEEDBACK
   ============================================= */

.form-error {
  font-size: var(--text-sm);
  color: var(--color-error);
  margin-top: var(--space-1);
}

.form-help {
  font-size: var(--text-sm);
  color: var(--text-muted);
  margin-top: var(--space-1);
}

.form-success {
  font-size: var(--text-sm);
  color: var(--color-success);
  margin-top: var(--space-1);
}

/* =============================================
   FORM LAYOUT
   ============================================= */

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-6);
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
}

.form-actions {
  display: flex;
  gap: var(--space-4);
  justify-content: flex-end;
  margin-top: var(--space-8);
}

@media (max-width: 640px) {
  .form-actions {
    flex-direction: column-reverse;
  }

  .form-actions .btn {
    width: 100%;
  }
}
```

---

### Card Component

**File**: `css/components/cards.css`

```css
/* =============================================
   CARD BASE
   ============================================= */

.card {
  background-color: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-xs);
  transition: all var(--transition-base);
}

.card:hover {
  box-shadow: var(--shadow-sm);
  border-color: var(--border-light);
}

.card-header {
  padding: var(--space-6);
  border-bottom: 1px solid var(--border-color);
}

.card-body {
  padding: var(--space-6);
}

.card-footer {
  padding: var(--space-6);
  border-top: 1px solid var(--border-color);
  background-color: var(--bg-tertiary);
  border-radius: 0 0 var(--radius-lg) var(--radius-lg);
}

/* Single section card */
.card > :only-child {
  padding: var(--space-6);
}
```

---

### Modal & Overlay

**File**: `css/components/modal.css`

```css
/* =============================================
   MODAL BACKDROP
   ============================================= */

.modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  z-index: var(--z-modal-backdrop);
  animation: fadeIn var(--transition-fast);
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

/* =============================================
   MODAL CONTAINER
   ============================================= */

.modal {
  background-color: var(--bg-primary);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-xl);
  max-width: 500px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  z-index: var(--z-modal);
  animation: slideUp var(--transition-base);
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  padding: var(--space-6);
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-title {
  font-size: var(--text-xl);
  font-weight: var(--font-weight-bold);
  color: var(--text-primary);
}

.modal-close {
  background: none;
  border: none;
  font-size: var(--text-xl);
  cursor: pointer;
  color: var(--text-secondary);
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  transition: all var(--transition-fast);
}

.modal-close:hover {
  background-color: var(--bg-secondary);
  color: var(--text-primary);
}

.modal-body {
  padding: var(--space-6);
}

.modal-footer {
  padding: var(--space-6);
  border-top: 1px solid var(--border-color);
  background-color: var(--bg-secondary);
  display: flex;
  justify-content: flex-end;
  gap: var(--space-4);
  border-radius: 0 0 var(--radius-lg) var(--radius-lg);
}

/* Large modal for history/details */
.modal.modal-lg {
  max-width: 700px;
}
```

---

### Table Styles

**File**: `css/components/tables.css`

```css
/* =============================================
   TABLE BASE
   ============================================= */

.table {
  width: 100%;
  border-collapse: collapse;
}

.table-container {
  width: 100%;
  overflow-x: auto;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  background-color: var(--bg-secondary);
}

.table {
  margin: 0;
}

/* =============================================
   TABLE HEADER
   ============================================= */

.table thead {
  background-color: var(--bg-tertiary);
}

.table th {
  padding: var(--space-4);
  text-align: left;
  font-size: var(--text-sm);
  font-weight: var(--font-weight-semibold);
  color: var(--text-secondary);
  border-bottom: 1px solid var(--border-color);
  user-select: none;
}

.table th.sortable {
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.table th.sortable:hover {
  background-color: var(--bg-secondary);
}

/* Sort indicator */
.table th.sort-asc::after {
  content: ' ↑';
  font-size: var(--text-xs);
}

.table th.sort-desc::after {
  content: ' ↓';
  font-size: var(--text-xs);
}

/* =============================================
   TABLE BODY
   ============================================= */

.table tbody tr {
  border-bottom: 1px solid var(--border-color);
  transition: background-color var(--transition-fast);
}

.table tbody tr:last-child {
  border-bottom: none;
}

.table tbody tr:hover {
  background-color: var(--bg-primary);
}

.table td {
  padding: var(--space-4);
  font-size: var(--text-sm);
  color: var(--text-primary);
}

/* =============================================
   TABLE SPECIAL ROWS
   ============================================= */

.table tr.expanded-row {
  background-color: var(--bg-tertiary);
}

.table tr.empty-row td {
  padding: var(--space-8);
  text-align: center;
  color: var(--text-muted);
  font-size: var(--text-sm);
}

/* =============================================
   TABLE ROW ACTIONS
   ============================================= */

.table-actions {
  display: flex;
  gap: var(--space-2);
  justify-content: flex-end;
}

.table-action-btn {
  padding: var(--space-2) var(--space-3);
  font-size: var(--text-xs);
  border-radius: var(--radius-sm);
}
```

---

## 🎨 UX Structure & Information Architecture

### Page Hierarchy & Flow

```
SETTINGS MANAGER
├── Page Header (H1)
│   ├── Title: "⚙️ Settings Manager"
│   ├── Subtitle: "Manage application settings with audit trail"
│   └── CTA: "+ New Setting" Button
│
├── Search & Filter Section (Card)
│   ├── Search Input (Full Width)
│   ├── Filter by Group Dropdown
│   └── Per Page Selector
│
├── Settings Table (Card)
│   ├── Column Headers (Sortable)
│   │   ├── Key (sortable)
│   │   ├── Type (sortable)
│   │   ├── Value (display truncated)
│   │   ├── Group (sortable)
│   │   └── Actions
│   │
│   ├── Table Rows
│   │   ├── Setting Data
│   │   ├── Expandable Row (show full value, meta)
│   │   └── Action Buttons (Edit, History, Delete)
│   │
│   └── Pagination Controls
│
├── Modals (Overlay)
│   ├── Create Setting Modal
│   ├── Edit Setting Modal
│   └── View History Modal
│
└── Notifications (Toast)
    ├── Success: "Setting created successfully"
    ├── Error: "Error: [message]"
    └── Info: "No settings found"
```

### Content Hierarchy & Visual Weight

| Element | Size | Weight | Color | Spacing | Use Case |
|---------|------|--------|-------|---------|----------|
| H1 (Page Title) | 30px (2.5rem) | 700 | text-primary | 24px below | Main page title |
| H2 (Section Title) | 24px (1.5rem) | 700 | text-primary | 16px below | Modal titles, section headers |
| H3 (Subsection) | 18px (1.125rem) | 600 | text-primary | 12px below | Group headers, subsections |
| Body Text | 16px (1rem) | 400 | text-primary | Normal line-height | Main content, table data |
| Labels | 14px (0.875rem) | 500 | text-secondary | 8px below | Form labels, column headers |
| Helper Text | 12px (0.75rem) | 400 | text-muted | 8px above | Help text, timestamps, hints |

### Responsive Breakpoints

```
Mobile-First Strategy:
├── 320px  → Base mobile layout
├── 640px  → Small tablets/landscape phones
├── 768px  → Tablets (standard breakpoint)
├── 1024px → Small laptops/large tablets
├── 1280px → Desktop
└── 1400px → Large desktop
```

---

## 💻 Component Interaction Patterns

### Search & Filter Pattern

**State Flow**:
```
User Input Search Term
    ↓
Debounce 300ms
    ↓
Query Settings (key, display_name)
    ↓
Filter by Group (if selected)
    ↓
Apply Sort
    ↓
Reset Pagination to Page 1
    ↓
Display Results
```

### CRUD Modal Pattern

**Create/Edit Flow**:
```
User Clicks "+ New" or "Edit"
    ↓
Open Modal with Form
    ↓
Populate Form Fields
    ↓
User Submits Form
    ↓
Validate (client-side + server)
    ↓
Success → Close Modal → Show Toast
    ↓
Error → Show Error in Form → Keep Modal Open
```

### History Modal Pattern

**View History Flow**:
```
User Clicks "History" Button
    ↓
Load 10 Most Recent Changes
    ↓
Display Timeline (newest first)
    ↓
Show: User, Action, Old→New, Timestamp, Reason
    ↓
User Clicks "Close"
    ↓
Modal Closes
```

### Expandable Row Pattern

**Expand Flow**:
```
User Clicks Row or Expand Icon
    ↓
Toggle `expandedRows` Array
    ↓
Show Additional Content (meta, full value)
    ↓
Display Expand/Collapse Icon State Change
```

---

## 🔄 Theme Toggle System

### Theme Architecture

```
Three Theme Modes:
├── Light (data-theme="light")
├── Dark (data-theme="dark")
└── System (data-theme removed → respects OS preference)

Selector Priority:
1. Explicit: [data-theme="light/dark"]
2. System: @media (prefers-color-scheme: dark)
3. Default: Light theme

Storage:
- localStorage.getItem('theme')
- If 'system': remove localStorage entry
```

### Theme Toggle Component (HTML)

```html
<div class="theme-toggle" role="radiogroup" aria-label="Theme selection">
  <button class="theme-toggle-option" data-theme="light" role="radio" aria-checked="false">
    <span aria-hidden="true">☀️</span> Light
  </button>
  <button class="theme-toggle-option" data-theme="dark" role="radio" aria-checked="false">
    <span aria-hidden="true">🌙</span> Dark
  </button>
  <button class="theme-toggle-option" data-theme="system" role="radio" aria-checked="true">
    <span aria-hidden="true">💻</span> System
  </button>
</div>
```

### Theme Toggle Styles

```css
.theme-toggle {
  position: relative;
  display: inline-flex;
  align-items: center;
  background-color: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-full);
  padding: 4px;
  gap: 4px;
  transition: all var(--transition-base);
}

.theme-toggle-option {
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: var(--font-weight-medium);
  color: var(--text-secondary);
  background-color: transparent;
  border: none;
  cursor: pointer;
  transition: all var(--transition-base);
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.theme-toggle-option:hover {
  color: var(--text-primary);
}

.theme-toggle-option.active {
  background-color: var(--color-primary);
  color: white;
  box-shadow: var(--shadow-sm);
}

.theme-toggle-option:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: -2px;
}
```

---

## 📱 Responsive Design Strategy

### Breakpoint Behavior

#### Mobile (< 640px)
- Full-width container with 16px padding
- Single-column form layout
- Stack modals full-screen
- Simplified table (hide non-essential columns)
- Buttons: Full width in modals
- Theme toggle: Positioned in header

#### Tablet (640px - 1024px)
- Two-column layout for appropriate sections
- Grid search & filter on single row
- Table shows all columns with horizontal scroll fallback
- Modal: Center with padding
- Form: Two-column layout available

#### Desktop (1024px+)
- Three-column layouts available
- Full table with all features visible
- Modals: Centered, max-width 500px
- Sidebar patterns possible
- Full feature set visible

### Mobile Optimizations

```css
/* Touch Targets: Minimum 44x44px */
.btn {
  min-height: 44px;
  min-width: 44px;
}

/* Readable Font Sizes */
input, select, textarea {
  font-size: 16px; /* Prevents zoom on iOS */
}

/* Spacing for Touch */
.table-actions .btn {
  padding: 8px 12px;
  gap: 4px;
}

/* Hide Non-Essential Columns */
@media (max-width: 768px) {
  .table-col-meta {
    display: none;
  }
}

/* Full-width Modals */
@media (max-width: 640px) {
  .modal {
    max-width: 100%;
    border-radius: 16px 16px 0 0;
    position: fixed;
    bottom: 0;
  }
}
```

---

## ♿ Accessibility Specifications

### Keyboard Navigation

**Must support**:
- Tab/Shift+Tab: Navigate through all interactive elements
- Enter: Activate buttons, submit forms, expand rows
- Escape: Close modals
- Arrow keys: Navigate within tables (optional enhancement)
- Space: Activate buttons, toggle checkboxes

### Screen Reader Support

**Semantic HTML**:
```html
<!-- Proper heading hierarchy -->
<h1>Settings Manager</h1>
<h2>Settings List</h2>

<!-- Form labels -->
<label for="search-input">Search Settings</label>
<input id="search-input" type="text" />

<!-- Tables with headers -->
<table>
  <thead>
    <tr>
      <th scope="col">Key</th>
      <th scope="col">Value</th>
    </tr>
  </thead>
</table>

<!-- Buttons with clear labels -->
<button aria-label="Delete setting">✕ Delete</button>
<button aria-label="View history for setting">📜 History</button>

<!-- Modal dialog -->
<div role="dialog" aria-labelledby="modal-title">
  <h2 id="modal-title">Create Setting</h2>
</div>
```

### Color Contrast

- **WCAG 2.1 AA Minimum**: 4.5:1 for normal text, 3:1 for large text
- **Text on Primary Blue**: White (21:1 contrast)
- **Text on Light Background**: Dark Gray (11:1 contrast)
- **Helper Text**: Gray 500 on White (7.5:1 contrast)

### Focus Management

```css
/* Clear focus indicators */
*:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

/* High contrast on dark backgrounds */
[data-theme="dark"] *:focus-visible {
  outline-color: var(--color-primary-light);
}
```

---

---

## 🔧 Technology Decision: Livewire vs Vue.js

### Why Livewire Is the Best Choice

**This Settings Manager is optimized for Livewire because**:

| Criteria | Settings Manager | Best Framework |
|----------|------------------|-----------------|
| **User Type** | Internal/Admin (developers) | Livewire ✅ |
| **Primary Operations** | CRUD + filtering + sorting | Livewire ✅ |
| **Interactivity Level** | Moderate (no real-time sync needed) | Livewire ✅ |
| **Form Validation** | Server-side with error feedback | Livewire ✅ |
| **Package Distribution** | Laravel package (no build step) | Livewire ✅ |
| **Developer Experience** | PHP-first, minimal JavaScript | Livewire ✅ |
| **Deployment** | Simple (same Laravel deployment) | Livewire ✅ |

### Livewire Advantages for This Use Case

- **CRUD is Native** — Create, edit, delete workflows built into Livewire's reactive model
- **Form Validation Built-in** — Server-side validation with automatic error display
- **No Build Step** — As a package, this stays distribution-friendly
- **Laravel Developers Get It** — PHP-based logic, Blade templates, familiar conventions
- **Faster Development** — 40-50% less code compared to Vue + API approach
- **Table Operations Built-in** — Sorting, pagination, row expansion out of the box
- **Modal Management** — Simple Livewire property toggles vs Vue component lifecycle
- **Search/Filter** — Wire directives handle reactivity automatically
- **History Timeline** — Single query + Blade template rendering

### When Vue Would Be Overkill

| Feature | Vue Priority | Settings Manager Need? |
|---------|--------------|------------------------|
| Real-time multi-user updates | Critical | ❌ Not needed |
| Animated transitions/effects | Nice-to-have | ❌ Not critical |
| Offline-first functionality | Important | ❌ No |
| Client-side state management | Essential | ❌ Server-side is fine |
| Single-page app experience | Core requirement | ❌ Admin panel works fine with full reloads |

---

## 💻 Developer Implementation Guide

### Priority Implementation Order

**Phase 1: Foundation (CSS Architecture)**
1. ✅ Create `css/design-system.css` - Establish all CSS variables
2. ✅ Create `css/typography.css` - Base typography scales
3. ✅ Create `css/layout.css` - Container and grid systems
4. Files: 3 | Time: 1-2 hours

**Phase 2: Components (Reusable Styles)**
1. ✅ Create `css/components/buttons.css` - All button variants
2. ✅ Create `css/components/forms.css` - Form inputs and states
3. ✅ Create `css/components/cards.css` - Card container
4. ✅ Create `css/components/modal.css` - Modal and overlay
5. ✅ Create `css/components/tables.css` - Table styles
6. Files: 5 | Time: 2-3 hours

**Phase 3: Layout Structure (HTML/Blade Components)**
1. Create Blade layout with header/footer
2. Build search & filter section
3. Build settings table with row actions
4. Implement all modals (create, edit, history)
5. Files: 1-2 | Time: 3-4 hours

**Phase 4: Livewire Integration**
1. Wire search/filter events
2. Wire CRUD modal events
3. Wire sort and pagination
4. Add toast notifications
5. Time: 2-3 hours

**Phase 5: Theme Toggle & Polish**
1. Implement theme manager JavaScript
2. Add theme toggle to header
3. Test light/dark/system modes
4. Responsive testing
5. Time: 1-2 hours

**Phase 6: Accessibility & QA**
1. Keyboard navigation testing
2. Screen reader testing (NVDA/JAWS)
3. Color contrast verification
4. Mobile responsiveness testing
5. Time: 2-3 hours

### File Structure

```
css/
├── design-system.css    (Variables, tokens, theme system)
├── typography.css       (Text styles, headings, scales)
├── layout.css          (Container, grid, flexbox patterns)
├── theme-toggle.css    (Theme toggle component styles)
└── components/
    ├── buttons.css     (Button variants and states)
    ├── forms.css       (Input, textarea, select styles)
    ├── cards.css       (Card container and sections)
    ├── modal.css       (Modal, backdrop, overlay)
    ├── tables.css      (Table, thead, tbody styles)
    └── index.css       (Import all components)

js/
├── theme-manager.js    (Theme switching logic)
└── main.js            (Livewire event binding, etc.)

resources/views/
├── layouts/app.blade.php           (Main layout with theme toggle)
└── livewire/
    ├── settings-manager.blade.php   (Settings UI template)
    └── components/
        ├── search-filter.blade.php
        ├── settings-table.blade.php
        ├── modals/
        │   ├── create-setting.blade.php
        │   ├── edit-setting.blade.php
        │   └── history-modal.blade.php
        └── notifications.blade.php
```

### Template Integration Example

**Main Layout** (`resources/views/layouts/app.blade.php`):

```blade
<!DOCTYPE html>
<html lang="en" data-theme="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Settings Manager')</title>
    
    <!-- CSS Files in correct order -->
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">
    <link rel="stylesheet" href="{{ asset('css/typography.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme-toggle.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components/buttons.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components/forms.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components/cards.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components/modal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components/tables.css') }}">
    
    @livewireStyles
</head>
<body>
    <!-- Header with Theme Toggle -->
    <header class="bg-secondary border-b border-border-color">
        <div class="container">
            <div class="flex-between py-4">
                <h1 class="text-heading-2">Settings Manager</h1>
                <div class="theme-toggle" role="radiogroup" aria-label="Theme selection">
                    <button class="theme-toggle-option" data-theme="light" role="radio">☀️ Light</button>
                    <button class="theme-toggle-option" data-theme="dark" role="radio">🌙 Dark</button>
                    <button class="theme-toggle-option active" data-theme="system" role="radio">💻 System</button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="min-h-screen bg-primary py-12">
        <div class="container">
            @yield('content')
        </div>
    </main>

    <!-- Notifications -->
    <div id="notifications"></div>

    @livewireScripts
    <script src="{{ asset('js/theme-manager.js') }}" defer></script>
    <script src="{{ asset('js/main.js') }}" defer></script>
</body>
</html>
```

---

## 🎓 Handoff Notes for Developers

### Key Principles

1. **CSS Variables First** — All colors, spacing, typography use CSS variables. Never hardcode values.
2. **Responsive Mobile-First** — Start mobile, enhance upward. All breakpoints use min-width media queries.
3. **Semantic HTML** — Structure HTML properly for accessibility. Use role attributes for screen readers.
4. **Component Reusability** — Build reusable component classes. Avoid one-off styles.
5. **Theme Consistency** — All colors respect `--text-primary`, `--bg-primary`, etc. Dark mode works automatically.

### Testing Checklist

- [ ] Light theme displays correctly with proper contrast
- [ ] Dark theme displays correctly with proper contrast
- [ ] System theme respects OS (prefers-color-scheme) preference
- [ ] Theme toggle persists across page reloads
- [ ] All breakpoints (320px, 640px, 768px, 1024px, 1280px) work correctly
- [ ] Keyboard navigation works (Tab, Enter, Escape)
- [ ] Focus indicators visible on all interactive elements
- [ ] Screen reader announces headers and form labels
- [ ] Touch targets minimum 44x44px on mobile
- [ ] No horizontal scroll on mobile (except for table scroll container)
- [ ] Modals are centered and readable on all devices
- [ ] Form validation messages display clearly
- [ ] Buttons have clear hover/active states
- [ ] All tables sortable columns show visual indicator

---

## 📚 Next Steps

This UX architecture provides:

✅ **Comprehensive CSS Design System** — Variables, tokens, theme system  
✅ **Layout Framework** — Responsive container, grid, flexbox patterns  
✅ **Component Library** — Buttons, forms, cards, modals, tables  
✅ **Accessibility Foundation** — Semantic HTML, keyboard nav, screen reader support  
✅ **Mobile-First Strategy** — Works perfectly on all device sizes  
✅ **Theme Toggle System** — Light/dark/system preference with persistence  
✅ **Developer Implementation Guide** — Phase structure, file organization, testing checklist  

**Ready for LuxuryDeveloper** to add premium polish, animations, and micro-interactions while maintaining this solid technical foundation.

---

**UX Architecture Document Created**  
**Date**: March 31, 2026  
**Next Phase**: Developer implementation of CSS foundation + Blade templates  
**Handoff Status**: ✅ Ready for development
