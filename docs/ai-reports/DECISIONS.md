# Architectural Decisions

Decisions recorded during implementation where the blueprint left room for
interpretation. Update this file whenever a new design question is resolved.

## AD-01 — Settings submission uses `admin_post`, not the Settings API

**Context:** `AdminModule` originally registered a Settings API field
(`register_setting('dls_starter_settings', 'dls_starter_options')`) *and* an
`admin_post_dls_starter_save_settings` handler writing a different option
(`dls_starter_api_key`), while the template posted a third field name
(`dls_starter[api_key]`). The three names never matched, so the registered
setting was dead code and `settings_errors()`/`settings_fields()` could not be
used safely.

**Decision:** Keep a single submission path via
`admin_post_dls_starter_save_settings` (as specified in the blueprint) and remove
the Settings API registration entirely. The canonical option is
`dls_starter_api_key`, read and written exclusively through
`Support\Options`.

**Rationale:** The blueprint mandates the `admin_post` handler with
`check_admin_referer()` + `current_user_can()` guards and an `Options` write. A
single path avoids the option-name drift that caused the original bug, and keeps
custom screens free of `options.php` redirect semantics.

**Consequences:** The screen does not participate in the Settings API, so
`settings_fields()` / `settings_errors()` are not used. Validation feedback is
passed through a `dls_starter_notice` query argument instead. If a future module
requires Settings API integration (e.g. WP-CLI `wp option` schema, REST
settings), it should register the option with `register_setting()` and a
matching `sanitize_callback` while keeping the same option name.

## AD-02 — Anonymous AJAX requests are allowed but never privileged

**Context:** `wp_ajax_nopriv_dls_starter_action` required `manage_options`, so
the public handler could only ever return 403. Removing the capability check
entirely would make an unauthenticated endpoint return privileged state.

**Decision:** Both handlers verify the same nonce (`dls_starter_action` in the
`security` field) via `wp_verify_nonce()` and return JSON errors instead of
dying on failure. The endpoint is capability-neutral: it echoes back the
sanitized input and only adds configuration state (`api_key_configured`) for
callers that hold `manage_options`.

**Rationale:** Keeps the documented public endpoint useful for
integrations while preventing disclosure of configuration state to anonymous
visitors. Nonce verification remains the CSRF boundary for both paths.

## AD-03 — Webhook authentication is header-based and deny-by-default

**Context:** The webhook endpoint required `manage_options`, which made the API
key comparison unreachable for genuine external callers. The API key was also
read from the request body, meaning credentials were logged with the payload.

**Decision:** `permission_callback` accepts either an authenticated
administrator session or a request presenting the configured API key in the
`X-DLS-API-Key` header (compared with `hash_equals()`). When no API key is
configured, external access is denied. Payloads are read with
`get_json_params()` (falling back to body params), depth- and field-count
limited, and recursively sanitized before being echoed back.

**Rationale:** Deny-by-default avoids an unauthenticated write surface on fresh
installs, headers keep secrets out of payload logs, and sanitizing the echoed
payload prevents reflected-injection issues in consumers.

## AD-04 — Asset ownership

**Context:** `assets/` shipped empty while `AdminModule` enqueued
`admin.css`/`admin.js` on every admin screen and the AJAX endpoint demanded a
nonce that no script provided. `AGENTS.md` §2.3 also requires every module to
own its assets.

**Decision:** Each module registers and enqueues its own assets:

- `AdminModule` enqueues `assets/css/admin.css` + `assets/js/admin.js` on its own
  page hook suffixes only, and localizes the AJAX nonce as
  `window.dlsStarterAdmin`.
- `FrontendModule` registers `assets/css/frontend.css` + `assets/js/frontend.js`
  on `wp_enqueue_scripts` and enqueues them only when the `[dls_starter]`
  shortcode actually renders, localizing `window.dlsStarterFrontend`.

**Rationale:** Enqueueing on demand keeps unrelated pages asset-free, and the
localized nonce makes the AJAX contract usable by the shipped scripts.

## AD-05 — `BaseRepository::countAll()` runs an unprefixed-value query

**Context:** `countAll()` passed a query with no placeholders to
`$wpdb::prepare()`, which triggers `_doing_it_wrong()` on WordPress 6.2+.

**Decision:** Validate the subclass table name against `^[A-Za-z0-9_]+$` inside
`getTableName()` and interpolate the resulting identifier directly, without
calling `prepare()`. Dynamic values continue to use `prepare()`.

**Rationale:** A table identifier cannot be parameterized; strict validation is
the correct mitigation, and `prepare()` must not be called without
placeholders.

## AD-06 — Uninstallation delegates to `Installer` and `OptionRepository`

**Context:** `uninstall.php` declared a global `dls_starter_cleanup_options()`
function (violating `AGENTS.md` §2.1), ran raw `$wpdb` queries inline, used
inconsistent formatting, and cleaned only the current site.

**Decision:** `uninstall.php` now only guards `WP_UNINSTALL_PLUGIN`, loads the
Composer autoloader, and calls `Installer::uninstall()`. Wildcard deletion lives
in `Database\OptionRepository`, which validates table/column identifiers against
a whitelist and binds prefixes through `$wpdb::prepare()` + `esc_like()`. The
`Installer` walks every site on multisite (`get_sites()` + `switch_to_blog()`)
and then clears network-wide `sitemeta` rows. If the autoloader is missing, a
closure fallback removes the current site's prefixed options so deletion never
leaves data behind.

**Rationale:** Keeps `AGENTS.md` §2.1 (no global functions) and §2.5 (database
encapsulation) intact, makes the removal logic testable, and adds multisite
coverage that the previous implementation lacked.

**Consequences:** Data removal is unconditional — there is no "keep data on
uninstall" opt-in. A plugin that needs that behaviour should introduce a
`dls_starter_remove_data_on_uninstall` option and check it before calling
`Installer::uninstall()`. Custom tables created through `BaseRepository` are not
dropped, because no concrete repository ships with the starter.
