=== Oven Cookie Consent ===
Contributors: wilcosky
Tags: cookies, consent, gdpr, privacy, cookie banner
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent for WordPress: detect cookies, classify essential vs non-essential, and re-prompt visitors when your cookie list changes.

== Description ==

Oven integrates the [CookieConsent](https://github.com/orestbida/cookieconsent) JavaScript library (v3.1.0) into WordPress to provide a compliant, accessible cookie consent experience.

= Features =

* **Cookie consent banner** – Shows a consent modal to visitors on first visit with Accept all, Essential only, and Manage preferences.
* **Automatic cookie detection** – Enable "Cookie detection mode" in settings, then browse your site while logged in as an administrator. Cookies set during your visit are detected and added to the list.
* **Essential vs non-essential** – WordPress core cookies (login, session, comments, settings) are classified as essential; third-party and analytics cookies as non-essential. Visitors see both categories in the preferences modal.
* **Revision and re-consent** – When a new cookie is detected and added, the revision number is incremented. All visitors (including those who had previously accepted) must consent again.
* **Logged-in vs guest** – For guests, consent is stored in a cookie so they are not prompted on every visit (until the revision changes). For logged-in users, consent is stored in the database (user meta) tied to their account.
* **Consent logging** – For logged-in users, each consent choice is recorded in their profile. Admins and editors can view when a user accepted and which cookies they accepted (Users → edit user → Cookie consent).
* **Cookie descriptions** – You can edit a short description for each cookie so visitors know what it does. Descriptions are shown in the preferences modal.
* **Privacy policy link** – Optional privacy policy URL in settings is shown in the consent modal (recommended for GDPR).
* **Accessibility** – Uses the CookieConsent library's built-in ARIA attributes and keyboard support.

= Settings =

* **Settings → Oven** – Enable cookie consent, enable cookie detection mode, set an optional privacy policy URL (recommended for GDPR), and view or edit the list of detected cookies and their descriptions.

= GDPR and compliance =

Oven is designed to support compliance with the GDPR and similar laws:

* **Consent before non-essential cookies** – Non-essential cookies and scripts that set them are blocked until the user accepts. Essential cookies (e.g. login, session) are allowed without consent.
* **Clear information** – Visitors see a list of cookies with names and descriptions (editable in settings). You can link to your privacy policy from the consent modal.
* **Granular choice** – Users can accept all, essential only, or manage preferences and toggle individual non-essential cookies.
* **Withdraw consent** – Users can reopen the preferences modal and change or withdraw consent at any time. Rejected non-essential cookies are cleared on each visit.
* **Re-consent when policy changes** – When you add or reclassify cookies, the revision number increases and all visitors are asked to consent again.
* **Consent logging (logged-in users)** – For registered users, consent choices and timestamps are stored in their profile. Admins and editors can view consent history under Users → [user] → Cookie consent. Guest consent is not logged (stored only in a cookie).
* **Transparency** – Cookie detection mode helps you build an accurate cookie list; you can add cookies manually and describe what each one does.

= Requirements =

* PHP 7.4 or higher
* WordPress 5.9 or higher

== Installation ==

1. Upload the plugin folder to `wp-content/plugins/`. The folder name (e.g. `Oven` or `oven`) is used in asset URLs; use the name that matches your server (case-sensitive on Linux).
2. Activate the plugin.
3. Go to **Settings → Oven** – enable "Enable cookie consent" and optionally "Cookie detection mode".
4. If using detection mode, browse your site as an admin to detect cookies; they will be added to the list and shown to visitors.

= Optional: Redirect wrong casing (Apache) =

If your plugin folder is `Oven` (capital O) and you want requests to `.../plugins/oven/...` to work, add this to `wp-content/plugins/.htaccess` (create the file if it doesn't exist):

RewriteEngine On
RewriteRule ^oven/(.*)$ Oven/$1 [L,R=301]

If your folder is `oven` (lowercase), use the opposite rule to redirect `Oven` to `oven`.

== Screenshots ==

1. The cookie preferences modal users see on the front end.
2. Cookie consent history in each user profile (back end).
3. Enable and auto detection settings (back end).
4. Manually add cookies (back end).
5. Cookies, ability to change from essential to non-essential and vice versa, and add descriptions (back end).
6. Add cookies by pattern and see which non-essential cookie scripts will be blocked unless the user accepts them or you change the cookie to essential (back end).

== Frequently Asked Questions ==

= The consent banner does not appear and the browser shows "MIME type 'text/html'" for the script. =

The server is returning an HTML page (often a 404) instead of the .js file. The plugin uses the actual folder name in URLs (e.g. `.../plugins/Oven/...`). Ensure the plugin folder name in `wp-content/plugins/` matches the URL. If your folder is `Oven`, don't rename it to `oven` (or the reverse) or asset URLs will 404. Optionally use the .htaccess redirect above so both casings work.

= Where is consent stored for logged-in users? =

Consent is stored in user meta under the key `oven_cookie_consent`. For guests, it is stored in a cookie named `oven_cc`.

= What happens when a new cookie is detected? =

When detection mode is on and you (as an admin) browse the site, any new cookie names are sent to the server, classified as essential or non-essential, and saved. The revision number is incremented so all visitors must re-accept the cookie policy.

== Changelog ==

= 1.0.9 =
* Security: route all cookie reads through Consent_Sanitizer; sanitize block attribute text; sanitize localized cookie names for head scripts.

= 1.0.8 =
* WordPress.org security review: sanitize $_COOKIE and $_POST JSON on read; escape admin HTML output (wp_kses_post, literal section wrappers).
* Head bootstrap uses static JS files with wp_localize_script() instead of wp_add_inline_script().
* Inline consent scripts use canonical JSON from sanitized consent payloads only.

= 1.0.7 =
* Text domain set to oven-cookie-consent to match WordPress.org plugin slug.
* Plugin URI set to https://wilcosky.com/oven (distinct from author URI).
* Plugin Check: nonce and input sanitization fixes in consent sanitizer and settings.

= 1.0.6 =
* Text domain aligned with plugin slug (oven) for WordPress.org and Plugin Check.
* Plugin Check: prefixed block render variables, input sanitization helpers, delete_metadata on uninstall.
* readme.txt: Tested up to 7.0, five tags, shorter short description; exclude .DS_Store from distribution.

= 1.0.5 =
* Contributors list uses the plugin owner's WordPress.org username (wilcosky).
* Removed Python locale build scripts from the plugin package; translations ship as .po/.mo files only.
* Added Consent_Sanitizer: all json_decode consent and script-mapping input is validated and sanitized before use or storage.
* Consent history uses sanitized consentId and consentTimestamp from stored data, not raw POST payload.

= 1.0.4 =
* WordPress.org review: Inline head scripts use the Script API (wp_register_script, wp_enqueue_script, wp_add_inline_script; early wp_print_scripts keeps tracer / cookie-clear / logged-in bootstrap order).
* Rely on core translation loading for the plugin text domain on WordPress.org (removed load_plugin_textdomain).
* Plugin URI points to a valid URL (https://wilcosky.com).
* Cookie Settings block: apiVersion 3 for WordPress 7.0 editor compatibility.

= 1.0.3 =
* Session cookie (oven_cc_sess) to avoid database reads on every request for logged-in users with consent.
* Session cookie tied to user ID so admin logout then regular user login does not reuse wrong consent.
* Clear session cookie after saving preferences so updated choices apply on next page load.
* LocalStorage for guests; sync from cookie or localStorage to database on login when possible.
* Preferences modal close button uses plugin SVG icon; secure cookie flag set from page protocol (HTTP/HTTPS).
* Re-accept after login when consent was given as guest, with sync to user meta; no double banner for same state.
* Cookie_Manager: use wp_unslash when reading guest consent cookie for consistent parsing.

= 1.0.1 =
* Cookie Settings Gutenberg block: add a block (Theme category) that shows a button to open the cookie preferences modal.
* Any element with the class `cookie-settings` now opens the preferences modal on click (block or custom links).
* Consent history on user profiles: prevent duplicate entries when the library fires multiple callbacks for one user action.
* Plugin naming and text domain aligned for WordPress.org (Plugin Name: Oven Cookie Consent; Text Domain: oven-cookie-consent).
* Readme and plugin header fixes: Tested up to 6.9; escape output and PHPCS adjustments.

= 1.0.0 =
* Initial release.
* Cookie consent banner with CookieConsent 3.1.0.
* Cookie detection mode for administrators.
* Essential/non-essential classification.
* Revision-based re-consent.
* User meta storage for logged-in users; cookie for guests.

== Credits ==

* CookieConsent (v3.1.0) by Orest Bida – https://github.com/orestbida/cookieconsent – MIT License.

== Upgrade Notice ==

= 1.0.9 =
Security hardening for WordPress.org plugin review.

= 1.0.8 =
Security hardening for WordPress.org review (input sanitization and output escaping).

= 1.0.7 =
Text domain and WordPress.org submission fixes (no functional changes for existing installs).

= 1.0.6 =
Plugin Check and WordPress.org compatibility (text domain, readme headers, distribution files).

= 1.0.5 =
Review fixes: contributor username, remove non-plugin files, sanitize decoded JSON consent data.

= 1.0.4 =
Maintenance release: WordPress.org review compliance (enqueueing inline scripts, translations, Plugin URI, block apiVersion).

= 1.0.3 =
Session cookie optimization, localStorage for guests, and fixes for regular users (save preferences, admin/regular user switch).

= 1.0.1 =
Cookie Settings block, consent history deduplication, and WordPress.org compatibility updates.

= 1.0.0 =
Initial release of Oven Cookie Consent.
