# Action Plan 01: Modular WordPress Starter Plugin Architecture

## Role & Objective

Act as a Senior WordPress Software Engineer. Implement the foundational layers, pluggable feature modules, and one-command rebranding CLI for the **Small-to-Medium (Two-Tier) WordPress Starter Plugin** (`DevlinkStudios\StarterPlugin\`).

All implementations must strictly adhere to the architectural boundaries defined in `AGENTS.md`. Do not write unit tests unless explicitly instructed. Log any unresolved architectural decisions in `docs/ai-reports/DECISIONS.md`.

---

## Phase 1: Core Support Layer (`core/Support/`)

Create lightweight, framework-agnostic utility classes to replace procedural global helpers.

### 1.1 `core/Support/Options.php`

- **Purpose:** Encapsulate WordPress `get_option`, `update_option`, and `delete_option` with automatic prefix enforcement (`dls_starter_`).
- **Contract:**
  - `public function get(string $key, mixed $default = null): mixed`
  - `public function set(string $key, mixed $value, bool $autoload = true): bool`
  - `public function delete(string $key): bool`
- **Constraint:** Prevent double-prefixing if the caller already passes a key starting with `dls_starter_`.

### 1.2 `core/Support/View.php`

- **Purpose:** Secure, isolated PHP template renderer that decouples presentation markup from controller scope (`$this`).
- **Contract:**
  - `public static function render(string $template, array $data = []): void`
  - `public static function make(string $template, array $data = []): string` (uses output buffering `ob_start()` / `ob_get_clean()`).
- **Security & Resolution:**
  - Resolve templates relative to `DLS_STARTER_PATH . '/views/'` (append `.php` automatically if omitted).
  - Reject any template path containing `..` to prevent directory traversal attacks by throwing `\InvalidArgumentException`.
  - Extract `$data` using `EXTR_SKIP` inside an isolated static closure so templates cannot access private/protected properties of calling controllers.

### 1.3 `core/Support/FormValidator.php`

- **Purpose:** Reusable request validation and error bag manager for Admin forms, AJAX, and REST endpoints.
- **Contract:**
  - `public function validate(array $data, array $rules): bool` (support baseline rules: `required`, `email`, `numeric`, `max:{len}`).
  - `public function errors(): array`
  - `public function hasError(string $field): bool`
  - `public function firstError(string $field): ?string`

---

## Phase 2: Database Layer (`core/Database/`)

Provide a clean `$wpdb` abstraction for medium-tier plugins requiring custom tables, while keeping the directory completely optional/deletable for micro-integrations.

### 2.1 `core/Database/BaseRepository.php`

- **Purpose:** Abstract base class encapsulating `global $wpdb` and enforcing safe prepared queries.
- **Requirements:**
  - Store `$wpdb` instance via constructor injection (`private ?\wpdb $db = null`, falling back to `$GLOBALS['wpdb']`).
  - Define abstract method `abstract protected function tableName(): string;` (returns unprefixed table name; base class prepends `$wpdb->prefix . 'dls_starter_'`).
  - Provide protected helpers: `findById(int $id): ?object`, `deleteById(int $id): bool`, and `countAll(): int`.

---

## Phase 3: Pluggable Feature Modules (`core/Modules/`)

Every module must implement `DevlinkStudios\StarterPlugin\Contracts\ModuleInterface`, keep its `__construct()` free of side-effects, register all WordPress hooks inside `register(): void`, and manage its own assets.

### 3.1 `core/Modules/Admin/AdminModule.php`

- **Responsibilities:**
  - Register top-level admin menu (`DLS Starter`, slug: `dls-starter-plugin`, capability: `manage_options`) and a `Settings` submenu on `admin_menu`.
  - Enqueue admin-only CSS/JS (`assets/css/admin.css`, `assets/js/admin.js` if files exist) strictly on plugin admin screens via `admin_enqueue_scripts` using `filemtime()` for cache busting.
  - Handle settings form submissions on `admin_post_dls_starter_save_settings` with strict `check_admin_referer()` and `current_user_can('manage_options')` guards, storing values via `Options` and redirecting back with a query notice.
- **Template:** Create `views/admin/settings.php` displaying a clean WordPress settings form using `esc_attr()`, `esc_html_e()`, and `wp_nonce_field()`.

### 3.2 `core/Modules/Frontend/FrontendModule.php`

- **Responsibilities:**
  - Register a baseline shortcode `[dls_starter]` via `init` hook.
  - Register frontend styles/scripts on `wp_enqueue_scripts` and enqueue them on-demand only when the shortcode renders.
- **Template:** Create `views/frontend/shortcode.php`.

### 3.3 `core/Modules/RestApi/RestApiModule.php`

- **Responsibilities:**
  - Hook into `rest_api_init` to register a health-check endpoint `GET /wp-json/dls-starter/v1/status` (public) and a protected settings/test endpoint `POST /wp-json/dls-starter/v1/webhook` (guarded by `permission_callback` checking `manage_options` or signature verification).
  - Return `WP_REST_Response` or `WP_Error` with proper HTTP status codes.

### 3.4 `core/Modules/Ajax/AjaxModule.php`

- **Responsibilities:**
  - Register authenticated (`wp_ajax_dls_starter_action`) and public (`wp_ajax_nopriv_dls_starter_action`) handlers.
  - Enforce `check_ajax_referer()` and send structured responses via `wp_send_json_success()` / `wp_send_json_error()`.

---

## Phase 4: One-Command Rebrander (`bin/rebrand.php`)

Implement a zero-dependency CLI tool invoked via `composer rebrand -- [flags]`.

### 4.1 CLI Interface & Flags

- **Required Flags:**
  - `--name="Plugin Name"` (e.g., `"DLS HubSpot Sync"`)
  - `--slug=plugin-slug` (e.g., `dls-hubspot-sync`)
  - `--vendor=vendor-slug` (e.g., `devlinkstudios`)
  - `--namespace=StudlyNamespace` (e.g., `HubSpotSync` — combined with StudlyCase vendor to form `DevlinkStudios\HubSpotSync`)
- **Optional Flags:**
  - `--dry-run` (Preview all file renames and token replacements without modifying disk)
  - `--clean` (Delete `bin/rebrand.php` after successful execution)

### 4.2 Deterministic Token Mapping & Execution Rules

- Derive identifiers automatically:
  - **Root Namespace:** `{StudlyVendor}\{StudlyNamespace}` (replaces `DevlinkStudios\StarterPlugin`)
  - **Composer Package:** `{vendor}/{slug}` (replaces `devlinkstudios/wp-starter-plugin`)
  - **Constant Prefix:** `{UPPER_SNAKE_SLUG}_` (replaces `DLS_STARTER_`)
  - **Option/Hook Prefix:** `{lower_snake_slug}_` (replaces `dls_starter_`)
  - **Text Domain / Slug:** `{slug}` (replaces `dls-starter-plugin`)
  - **REST Namespace:** `{slug}/v1` (replaces `dls-starter/v1`)
  - **Human Name:** `{name}` (replaces `DLS Starter Plugin` and `DLS Starter`)
- **Validation:** Refuse execution if any required flag is missing, malformed, or matches the default starter values.
- **File Operations:**
  - Recursively scan `.php`, `.json`, and `.md` files (excluding `vendor/`, `.git/`, and `bin/rebrand.php` itself).
  - Rename `dls-starter-plugin.php` to `{slug}.php`.
  - Execute `composer dump-autoload -o` via `passthru()` after rewriting `composer.json` (unless `--dry-run` is active).

---

## Definition of Done

1. All classes use `declare(strict_types=1);`, typed properties, and constructor property promotion where applicable.
2. Deleting any single folder inside `core/Modules/` and removing its class reference from `Plugin::getActiveModules()` results in zero PHP warnings or fatal errors.
3. Running `composer rebrand -- --dry-run --name="DLS HubSpot Sync" --slug=dls-hubspot-sync --vendor=devlinkstudios --namespace=HubSpotSync` outputs a complete, accurate diff plan without touching files.
