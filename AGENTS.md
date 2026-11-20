# AGENTS.md — Architectural & Coding Specifications

## 1. Core Identity & Architecture

- **Vendor Namespace:** `DevlinkStudios\StarterPlugin\` mapped to `core/` via PSR-4.
- **Paradigm:** Modular, Lightweight, Two-Tier Architecture (Zero-dependency by default, Container-ready via `Plugin::resolveModule()`).
- **PHP Standard:** PHP 8.1+ with `declare(strict_types=1);` at the top of every PHP file.
- **Documentation:** All DocBlocks must be written in professional, industry-standard English.

## 2. Strict Architectural Boundaries

1. **No Global Procedural Functions:** Never create `functions.php` or global helper functions. Encapsulate all utilities inside `core/Support/`.
2. **Constructor Purity:** Module constructors (`__construct`) must ONLY accept dependencies. Never call `add_action()`, `add_filter()`, or `add_shortcode()` inside a constructor. All hook registrations belong exclusively inside `ModuleInterface::register(): void`.
3. **Self-Contained Modules:** Every feature under `core/Modules/{ModuleName}/` must register its own assets (`admin_enqueue_scripts` / `wp_enqueue_scripts`), hooks, and handlers so any module directory can be deleted cleanly without breaking the core.
4. **View Isolation:** Never use raw `include` or `require` inside controllers or handlers. Always render HTML templates from `views/` using `DevlinkStudios\StarterPlugin\Support\View::render()`.
5. **Database Encapsulation:** Never use `global $wpdb` directly inside Controllers, REST endpoints, or AJAX handlers. Isolate all database queries inside dedicated Repository classes under `core/Database/`.
6. **Prefix Discipline:**
   - Constants: `DLS_STARTER_*`
   - Options / Hooks / DB Tables: `dls_starter_*`
   - Text Domain / Slug: `dls-starter-plugin`
   - REST Namespace: `dls-starter/v1`

## 3. AI Workflow & Decision Logging

- Follow step-by-step execution plans located in `docs/ai-plans/`.
- If any architectural ambiguity or unaddressed design decision arises during implementation, do not guess silently—document it clearly in `docs/ai-reports/DECISIONS.md`.
- Do not execute `git push`; repository pushes are handled manually.
