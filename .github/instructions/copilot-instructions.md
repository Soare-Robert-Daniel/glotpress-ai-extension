---
applyTo: "**"
---

# Project-wide Coding Guidelines

The rules below define the **minimum** quality bar for any AI-generated code in this repository.  
When language-specific guidance conflicts with a general rule, the language-specific guidance takes precedence.

---

## General Principles

-   Priorities readability and maintainability over cleverness.
-   Keep functions small and single-purpose; extract helpers instead of nesting deeply.
-   Fail fast: validate inputs early and raise explicit errors.
-   Use clear, descriptive names—avoid abbreviations and “tmp” variables.
-   Document _why_ (JSDoc/PHPDoc) when intent is not obvious.
-   Avoid hidden side-effects; make state changes explicit.

---

## JavaScript / TypeScript

-   Use **`const`** for values that never reassign; **`let`** otherwise. **Never use `var`.**
-   Prefer arrow functions for inline or callback functions.
-   Rely exclusively on strict equality (`===`, `!==`).
-   Build strings with template literals.
-   Avoid `any`; declare explicit types or generics instead.
-   Guard against `null` / `undefined` (use optional chaining and nullish coalescing).
-   Destructure objects/arrays where it improves clarity.
-   Prefer `async/await`; avoid promise chains.
-   Limit nesting depth to two blocks; extract logic into helpers.
-   Export multiple symbols via **named exports**; reserve default exports for legacy interop.

---

## PHP (WordPress)

-   Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) for PHP, HTML, JS, and CSS.
-   Namespace or prefix **all** functions, classes, and globals to prevent collisions.
-   Escape output (`esc_html()`, `esc_attr()`, …) and sanitise input (`sanitize_text_field()`, `intval()`, …).
-   Secure state-changing actions with nonces **and** capability checks.
-   Localise strings with `__()` / `_e()` and include a text-domain `'glotpress-ai-extension'`.
-   Use `$wpdb->prepare()` or higher-level APIs—never concatenate SQL.
-   Register hooks/filters instead of editing core files.
-   Generate URLs/paths via helpers (`home_url()`, `plugin_dir_path()`, …).
-   Enqueue assets with `wp_enqueue_script|style()`; respect dependencies and versions.
-   Avoid deprecated or insecure APIs (e.g. `create_function`).
-   Document with PHPDoc, including `@since` and `@return`.
-   Test new logic with PHPUnit.
-   PHPDocs type must be compatible with PHPStan.

---
