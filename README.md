# reMember

A WordPress plugin for membership-style communities: **members**, **events**, **locations**, **applications**, **vetting**, **billing** (including **QuickBooks Online**), and **role-based access**. It extends WordPress users with custom tables, an admin UI under **reMember**, and front-end templates (shortcodes and block patterns).

**Version:** 1.1.0  
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

**Branching note:** Release work for this line lives on branch `v1.1.1`. Prefer clear conventional commits so the changelog below stays easy to inventory across machines.

**Accounting providers:** One active billing provider per site (`quickbooks` or `xero`). Parallel stacks; see [`XERO_PLAN.md`](XERO_PLAN.md).

---

## Changelog

### 1.1.1

- **Version** bumped to `1.1.1` (`REMEMBER_VERSION`).
- **Member billing:** Invoice # links to the **customer** Xero online invoice (`in.xero.com`) when `xero_online_invoice_url` is stored on the payment (schema `1.17.0`, filled on invoice create/sync). Admin deep-links unchanged. QuickBooks member rows stay unlinked.
- **Fix:** Xero payment sync no longer treats auth errors like “Refresh token not found” as a missing invoice (that was wiping invoice # and amounts on the dashboard). QuickBooks missing-invoice detection tightened the same way.

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
