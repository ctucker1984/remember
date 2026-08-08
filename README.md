# reMember

A WordPress plugin for membership-style communities: **members**, **events**, **locations**, **applications**, **vetting**, **billing** (including **QuickBooks Online**), and **role-based access**. It extends WordPress users with custom tables, an admin UI under **reMember**, and front-end templates (shortcodes and block patterns).

**Version:** 1.3.0 (in progress)  
**Requires:** WordPress 5.0 or higher  
**License:** GPL v2 or later

---

## What this plugin does

reMember models your organization as **members** (backed by WordPress users), **events** at **locations**, with **roles** and optional **products** (add-ons / merchandise) per event. Members submit **applications**; staff **vet** new members and process applications. When an application is accepted, **payments** can be tracked and synced with **QuickBooks** invoices.

The **admin** area is where you configure data and workflows. The **front end** uses shortcodes on ordinary WordPress pages (often created via the built-in setup wizard).

For **database table and column detail**, see `ARCHITECTURE.md` in this folder.

---

## Requirements

- **PHP:** A version supported by your WordPress install (OpenSSL recommended if you use encrypted storage for OAuth tokens).
- **MySQL/MariaDB:** Standard WordPress database; custom `remember_*` tables are created on activation and upgraded over time via schema migrations.
- **Permalinks:** Pretty permalinks are recommended if you rely on front-end URLs and shortcodes in a typical way.

---

## Installation

1. Copy the `remember` folder into `wp-content/plugins/`.
2. In **Plugins → Installed Plugins**, activate **reMember**.
3. On activation, the plugin creates database tables, seeds initial data (including default roles), and grants **reMember capabilities** to the WordPress **Administrator** role (and to the user who activated the plugin). It does **not** assume any WordPress user is a member—no member row, profile, or System Administrator role is auto-assigned.
4. You are redirected to the **setup wizard** (for a limited time) to map shortcodes to pages; you can also open it anytime from **reMember → Getting Started** or **Settings**.
5. When you are ready, add members via **Members → Add New**, **Members → Convert WP User** (existing accounts), or the public **member registration** shortcode.

---

## First-time setup: static data

Configure foundation data **before** members apply to events. Recommended order:

1. **Locations** — **reMember → Locations** — venues and addresses used when creating events.
2. **Roles** — **reMember → Roles** — participation roles offered on events (and system roles that gate admin capabilities).
3. **Products** — **reMember → Products** — optional add-ons (merchandise, fees) tied to events; when using QuickBooks, map items under **reMember → Settings** as needed.
4. **Events** — **reMember → Events** — create each event (location, dates, status), attach **event roles** and optional **add-ons**.

This order is also explained in **reMember → Getting Started** (always available) and at the top of the **setup wizard** after activation.

**Front-end pages:** Use **reMember → Settings** (shortcode reference) or the **setup wizard** to create WordPress pages and insert shortcodes (member dashboard, events list, profile, applications, etc.).

---

## Admin areas (overview)

| Area | Purpose |
|------|---------|
| **Dashboard** | Summary and quick access for administrators. |
| **Getting Started** | Step-by-step static data setup and links to the setup wizard and shortcode docs. |
| **Members** | Member records tied to WordPress users; status, profile, roles. **Add New** creates a WP user + member. **Convert WP User** turns an existing non-member WP account into a member (affirmative confirmation required). |
| **Events** | Events with locations, privacy, dates, role slots, and merchandise. |
| **Applications** | Application workflow for joining or participating in events. |
| **Waitlist** | Waitlisted applications. |
| **Vetting** | Review and approval workflow for new members. |
| **Billing** | Payment records; **QuickBooks** invoice numbers and amounts when connected. |
| **Locations** | Venues and addresses. |
| **Roles** | Custom roles (event vs system) and capability assignments. |
| **Products** | Catalog items (add-ons) for events; can align with QuickBooks products. |
| **Settings** | Plugin options, shortcode documentation, **QuickBooks OAuth** and sync, notifications, and related tools. |
| **Import / Export** | CSV import/export for members, events, and locations (runs early on `admin_init` so downloads can send headers correctly). |

---

## Typical member and staff flows

1. **Become a member** — Public registration (`[remember_register]` / `[remember_registration]`), **Members → Add New**, or **Members → Convert WP User**. Site admins and other WP users are **not** members until one of these paths is used.
2. **Vetting** — If enabled, staff review the member in **Vetting** until **vetted** (or rejected).
3. **Profile** — Members maintain legal name (private), public nickname / display name, contact info, dietary / medical / allergy selections, photo, and **Share with Event Members** privacy toggles on the front-end profile.
4. **Events & applications** — Members browse events (front end) and submit **applications** for specific event roles (and optional add-ons).
5. **Staff** — Accept or decline applications in **Applications**; waitlist as needed.
6. **Billing** — For accepted applications, payments may be created and synced with the active accounting provider (**QuickBooks** or **Xero**); staff can reconcile in **Billing** (amounts shown in reMember are subtotals; invoice numbers come from the provider).

---

## Names and privacy (members)

- **Username** — WordPress login; cannot be changed later.
- **Display name** — Required at registration; how the member appears publicly. Never auto-derived from legal name.
- **Nickname** — WordPress nickname; editable on the profile; available as a display-name choice (same idea as WP core).
- **Legal name** — Stored on the member profile for admin / vetting / billing contacts; labeled private on the member’s own dashboard and profile view. Not shown in the event attendee directory.
- **Share with Event Members** — Opt-in toggles (email, phone, location, IM, interests, **profile photo**). The event directory only shows what the member enabled.
- **Dietary restrictions / medical accommodations / allergies** — Editable by the member on the front-end profile and by staff in admin; for organizers, not shared in the public directory.

---

## Billing and accounting providers

Choose **one** active provider under **reMember → Settings → General** (`none`, **QuickBooks Online**, or **Xero**). Switching does not delete historical invoice IDs on payment rows; finish open cycles before switching.

### QuickBooks Online

- Connect under **Settings → QuickBooks** (OAuth credentials from the Intuit developer console).
- Create invoices on application accept, sync payment/refund lines, store invoice numbers (`DocNumber`) for **Billing** and the member register.
- Scheduled sync uses the shared `remember_qb_sync` cron; **Billing** can also refresh when that screen loads (when QBO is active and connected).

### Xero

- Connect under **Settings → Xero** (OAuth app redirect URI is built from `admin_url()`; register each site hostname in the Xero app).
- Parallel to QBO: contact sync (legal name, email/phone/address, nickname in Notes, WP user ID as AccountNumber), role/product item mapping, invoices on accept, payment + credit-note sync into the register.
- Plan and phase notes: [`XERO_PLAN.md`](XERO_PLAN.md).

### Shared accounting note

Displayed amounts in reMember are **subtotal-oriented**; taxes and final totals are authoritative in the active provider and its customer-facing emails.

---

## Front end

- Shortcodes are documented under **Settings** (Shortcodes tab / anchor `#shortcodes`).
- Block patterns may be included in the plugin for common layouts; shortcodes remain the primary integration surface.
- **Profile edit** includes public identity (nickname + display name), health/accommodation checklists, privacy toggles, and **profile photo** upload with circular preview, zoom, and drag-to-recenter before save.

---

## Capabilities

reMember adds granular capabilities (`remember_read_events`, `remember_create_members`, `remember_access_settings`, etc.). The WordPress **Administrator** role receives these capabilities on activation so staff can manage the plugin without becoming members. Users can also receive capabilities from **reMember roles** (`Remember_Capabilities::sync_user_capabilities_from_roles` on login and when roles change). Activation does **not** auto-create a reMember member for the installing user—convert an existing WP user via **Members → Convert WP User**, create a new member with **Add New**, or use public registration.

---

## Development

| Path | Role |
|------|------|
| `remember.php` | Bootstrap, activation hooks, version constant. |
| `includes/class-remember.php` | Loads admin/public, registers hooks (cron, OAuth, setup wizard, etc.). |
| `admin/class-remember-admin.php` | Admin menu, pages, AJAX, QuickBooks OAuth handlers, setup wizard. |
| `admin/views/` | Admin screens (including `setup-wizard.php`, `getting-started.php`, `partials/`). |
| `public/class-remember-public.php` | Front-end assets and shortcode handlers. |
| `public/partials/` | Front-end templates (dashboard, profile, register, event directory, etc.). |
| `includes/models/` | Data models (members, events, payments, etc.). |
| `includes/database/` | Table creation, migrations (`Remember_Database_Updater`), seeding. |
| `includes/integrations/` | QuickBooks and Xero OAuth/API/sync helpers (one active provider at a time). |

Enable `WP_DEBUG` as needed; logging uses `Remember_Logger`.

**Branching note:** Active work is on `v1.3.0`; merge to `master` after tagging. Prefer clear conventional commits for the changelog.

**Accounting providers:** One active billing provider per site (`quickbooks` or `xero`). Parallel stacks; see [`XERO_PLAN.md`](XERO_PLAN.md).

### Installing / upgrading from a GitHub Release

1. Download **`remember-x.y.z.zip`** from the release **Assets** — not GitHub’s auto “Source code (zip)”.
2. Our zip filename is versioned, but it unpacks to **`remember/`** (WordPress-safe). GitHub’s source archive unpacks as `remember-<tag>/` and breaks upgrades — ignore it.
3. **Upgrade tip:** From **1.3.0+**, Upload → Replace deactivates reMember, replaces files, then silently reactivates. When upgrading **from ≤1.2.x to 1.3.0**, deactivate reMember first (or use a 1.2.1 hotfix that only adds that behavior), then upload and activate.
4. Build locally: `bash bin/build-plugin-zip.sh` → `dist/remember-<version>.zip`, then attach on the GitHub Release.

---

## Changelog

### 1.3.0

- **Version** bumped to `1.3.0` (`REMEMBER_VERSION`).
- **Upgrade safety:** On zip Upload → Replace, silently deactivate reMember before clearing `remember/`, then silently reactivate (matches dashboard update behavior). Does not help sites still running ≤1.2.0 until this version is installed once.
- **Custom profile fields:** Admin-defined questions (free text, single-select, or multi-select with key/label options), export field keys for member CSV, shown on registration and profile. Schema `1.23.0`.
- **Agreements:** Versioned library (rules, waivers, etc.); events pin revisions; applicants acknowledge each with typed legal name on apply. Schema `1.24.0`.
- **Allow reapply:** Admin can supersede declined/cancelled applications so the member may apply again (schema `1.25.0`).
- **Admin profile flexibility:** Member edit only requires nickname, display name, legal first/last, and cell phone.
- **Event participant CSV:** On admin event detail, export accepted participants (profile, custom field short names/keys, role, per–add-on quantities). Capability `remember_event_data_export` (Event Administrator + System Administrator). Schema `1.26.0`.
- **Import/Export capability:** Settings → Import/Export requires `remember_import_export` (System Administrator by default; assignable on Roles). Schema `1.27.0`.
- **Member / custom-field CSV:** Member export includes nickname, sizes, dietary/allergies/medical, timezone, and custom Short name columns; separate Custom Fields definition export/import for demo→prod moves.

### 1.2.0

- **Version** bumped to `1.2.0` (`REMEMBER_VERSION`).
- **Admission tickets:** Printable HTML ticket + receipt; stamps **PAYMENT REQUIRED** / **VOID**; member/admin print; emails on accept / paid / balance-due. Schema `1.18.0`.
- **Polish:** Rich text for locations, event description, Interests; single timezone combobox; emergency phone required; admin Declined / member Cancelled with void–refund–leave invoice prompt and ticket VOID.
- **Registration / profile:** Full profile on register; shared required-field policy; clothing sizes shirt/pants S–6XL, shoes 6–15. Schema `1.19.0` / `1.21.0`.
- **Events:** Per event×role add-on max qty (0 = hide); attendee-only event details. Schema `1.20.0` / `1.22.0`.
- **Billing:** Email Xero/QBO invoice on accept (Settings toggle; soft-fail); sync on member detail + Billing list; Xero credit notes credit the ledger; Payment/Credit column includes credits; sort by document date.
- **Release packaging:** `bin/build-plugin-zip.sh` → `remember-1.2.0.zip` unpacks to `remember/`. Ignore GitHub’s auto “Source code” zip.

### 1.1.2

- **Version** bumped to `1.1.2` (`REMEMBER_VERSION`).
- **Registration photo:** Optional profile photo on member register with the same circular **zoom** + **drag-to-recenter** cropper as profile edit.
- **Admin member edit:** Same photo cropper on Members → Edit Profile.
- **Pages:** Install / page creator uses title **Member Registration** (capital R); FSE pattern title aligned.
- **Repo:** `.github/` ignored / untracked (release zips built locally via `bin/build-plugin-zip.sh`).
- Printable admission tickets deferred — see [`TODO_TICKETS.md`](TODO_TICKETS.md).

### 1.1.1

- **Version** bumped to `1.1.1` (`REMEMBER_VERSION`).
- **Member billing:** Invoice # links to the **customer** Xero online invoice (`in.xero.com`) when `xero_online_invoice_url` is stored on the payment (schema `1.17.0`, filled on invoice create/sync). Admin deep-links unchanged. QuickBooks member rows stay unlinked.
- **Fix:** Xero payment sync no longer treats auth errors like “Refresh token not found” as a missing invoice (that was wiping invoice # and amounts on the dashboard). QuickBooks missing-invoice detection tightened the same way.
- **Release packaging:** `bin/build-plugin-zip.sh` builds `remember-x.y.z.zip` with root folder `remember/` (versioned filename; unpack path is not). Ignore GitHub’s auto “Source code” zip.

### 1.1.0

- **Version** bumped to `1.1.0` (`REMEMBER_VERSION`).
- **Billing provider:** Settings → General radio (`none` / QuickBooks / Xero). Subtotal disclaimer follows the active provider.
- **Xero:** Full parallel path to QBO — OAuth (granular scopes), contact sync, item mapping, invoices on accept / reprocess (clears voided links), payment + credit-note sync into Billing / member register, Settings sync controls. Schema `1.16.0` (`xero_*` columns, item mappings, processor). See `XERO_PLAN.md`.
- **QuickBooks:** Unchanged operationally; gated to the QuickBooks provider selection where dispatch was updated.

### 1.0.1

- **Version** bumped to `1.0.1` (`REMEMBER_VERSION`).
- **Registration:** Required **Display Name**; legal first/last labeled as legal/private; username help text (login; not changeable). New accounts never default `display_name` from legal name; nickname is seeded from the chosen display name.
- **Profile (front end):** Edit nickname and “Display name publicly as” (WP-style choices). Dietary restrictions, medical accommodations, and known allergies (always shown on view with **None Selected** when empty). Profile photo upload/replace/delete with circular live preview, zoom, and drag-to-recenter; crop applied on save.
- **Privacy:** `share_photo_with_events` column (DB `1.15.0`); Share Profile Photo toggle in admin and front-end profile; event directory respects photo sharing. Legal name no longer resolved from `display_name` in list/import helpers.
- **Membership on install:** Activation no longer creates a member/profile/System Administrator role for the activating user. **Members → Convert WP User** converts an existing non-member WP account with explicit confirmation.
- **Admin Add Member:** New WP users created from Add New get display name/nickname from username (not legal name).
- **Phone requirements:** Member cell phone required (registration, front-end profile, admin edit). Emergency contact phone is optional.
- **Registration time zone:** Required on signup; saved to WordPress user meta `timezone_string` (same place as profile/admin).
- **Xero (planned):** Parallel billing provider (one active provider per site); see `XERO_PLAN.md`.

### 1.0.0

- Initial baseline release: members, events, applications, vetting, billing/QuickBooks, roles, locations, products, shortcodes, and admin UI.

---

## Contributing and support

Plugin URI and source references: see the plugin header in `remember.php`. Use your project’s GitHub workflow for issues and pull requests if applicable.

---

## License

This plugin is released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
