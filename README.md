# reMember

WordPress membership communities: **members**, **events**, **locations**, **applications**, **vetting**, **admission tickets**, and **billing** with **QuickBooks Online** or **Xero**. Extends WordPress users with custom tables, an admin UI under **reMember**, and front-end pages via shortcodes.

**Version:** 2.0.0  
**Requires:** WordPress 5.0+  
**License:** [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html)  
**Source:** [github.com/ctucker1984/remember](https://github.com/ctucker1984/remember)

---

## Install

1. Download **`remember-x.y.z.zip`** from the [GitHub Releases](https://github.com/ctucker1984/remember/releases) **Assets** — not GitHub’s auto “Source code (zip)” (that unpacks to the wrong folder name).
2. In WordPress: **Plugins → Add New → Upload Plugin**, then activate **reMember**.
3. On activation the plugin creates tables, seeds default roles, and grants reMember capabilities to the WordPress **Administrator** role. It does **not** auto-create a member for the activating user.
4. Use the **setup wizard** (or **reMember → Getting Started**) to create pages with shortcodes.

**Migration:** Activate reMember on the destination site (same version as the backup, or newer), then **reMember → Import / Export → Restore backup**. A newer backup will not load into an older install. Users are matched by email (then username); missing people are created as Subscribers with random passwords until they reset. Profile photos are URLs only. Setup-wizard pages stay on the new site.

Members log in through WordPress (`wp-login.php` or the site’s login). reMember uses that session; there is no separate member login form.

**Updates:** From **1.3.5+**, new releases appear under **Plugins → Updates** in wp-admin (WordPress 5.8+). reMember reads [GitHub Releases](https://github.com/ctucker1984/remember/releases) and installs the packaged `remember-x.y.z.zip`. Getting to 1.3.5 itself still requires one manual upload.

**Upgrade tip:** From **1.3.0+**, Upload → Replace deactivates reMember, replaces files, then reactivates. When upgrading **from ≤1.2.x**, deactivate reMember first, then upload and activate.

---

## First-time setup

Configure foundation data before members apply:

1. **Locations** — venues for events  
2. **Roles** — event and system roles / capabilities (members may hold more than one)  
3. **Products** — optional event add-ons (map to QBO/Xero items in Settings if needed)  
4. **Events** — attach location, roles, and add-ons  

Then add members via **Members → Add New**, **Convert WP User**, or the public registration shortcode. Shortcode reference: **reMember → Settings**.

---

## Features (overview)

| Area | Notes |
|------|--------|
| **Members** | Profiles (legal name private; public nickname / display name), photo cropper, privacy toggles, custom fields, clothing sizes, dietary / medical / allergies, profile notes (member-visible or private to wp-admin) |
| **Duplicates** | Daily scan of possible duplicate profiles; staff with **Merge Duplicate Profiles** review side by side, pick surviving fields and roles, then merge or undo. Members are emailed only after a merge. |
| **Reports** | Staff wp-admin builder (not raw SQL): subjects, columns, filters, grouping, AJAX preview, CSV, and per-user saved reports. Copy a saved report to another staff member who can run it. `View Reports` opens the screen; each column still needs the matching read cap. |
| **Events** | Locations, roles, add-ons, attendee directory, printable admission tickets |
| **Applications** | Review and save profile first, then apply / accept / decline / waitlist; optional agreements with typed legal name; allow reapply after decline/cancel |
| **Vetting** | New-member review workflow |
| **Billing** | One active provider: none, QuickBooks Online, or Xero; amounts in reMember are subtotal-oriented |
| **Import / Export** | CSV for members, events, locations, and custom field definitions. Full JSON backup and restore of plugin tables and settings (needs Access Settings and Import / Export Data). Restore can migrate onto a fresh install. |
| **Agreements** | Versioned library; events pin revisions shown on apply |
| **Notifications** | Email templates in Settings, grouped by Vetting, Applications, Billing, and General |

---

## Changelog

See **[CHANGELOG.md](CHANGELOG.md)** for the full version history. Release notes are also on [GitHub Releases](https://github.com/ctucker1984/remember/releases).
