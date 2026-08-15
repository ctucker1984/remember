# reMember

WordPress membership communities: **members**, **events**, **locations**, **applications**, **vetting**, **admission tickets**, and **billing** with **QuickBooks Online** or **Xero**. Extends WordPress users with custom tables, an admin UI under **reMember**, and front-end pages via shortcodes.

**Version:** 1.3.4  
**Requires:** WordPress 5.0+  
**License:** [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html)  
**Source:** [github.com/ctucker1984/remember](https://github.com/ctucker1984/remember)

---

## Install

1. Download **`remember-x.y.z.zip`** from the [GitHub Releases](https://github.com/ctucker1984/remember/releases) **Assets** — not GitHub’s auto “Source code (zip)” (that unpacks to the wrong folder name).
2. In WordPress: **Plugins → Add New → Upload Plugin**, then activate **reMember**.
3. On activation the plugin creates tables, seeds default roles, and grants reMember capabilities to the WordPress **Administrator** role. It does **not** auto-create a member for the activating user.
4. Use the **setup wizard** (or **reMember → Getting Started**) to create pages with shortcodes.

**Upgrade tip:** From **1.3.0+**, Upload → Replace deactivates reMember, replaces files, then reactivates. When upgrading **from ≤1.2.x**, deactivate reMember first, then upload and activate.

---

## First-time setup

Configure foundation data before members apply:

1. **Locations** — venues for events  
2. **Roles** — event and system roles / capabilities  
3. **Products** — optional event add-ons (map to QBO/Xero items in Settings if needed)  
4. **Events** — attach location, roles, and add-ons  

Then add members via **Members → Add New**, **Convert WP User**, or the public registration shortcode. Shortcode reference: **reMember → Settings**.

---

## Features (overview)

| Area | Notes |
|------|--------|
| **Members** | Profiles (legal name private; public nickname / display name), photo cropper, privacy toggles, custom fields, clothing sizes, dietary / medical / allergies |
| **Events** | Locations, roles, add-ons, attendee directory, printable admission tickets |
| **Applications** | Apply / accept / decline / waitlist; optional agreements with typed legal name; allow reapply after decline/cancel |
| **Vetting** | New-member review workflow |
| **Billing** | One active provider: none, QuickBooks Online, or Xero; amounts in reMember are subtotal-oriented |
| **Import / Export** | Members, events, locations, custom field definitions (capability-gated) |
| **Agreements** | Versioned library; events pin revisions shown on apply |

---

## Changelog

See **[CHANGELOG.md](CHANGELOG.md)** for the full version history. Release notes are also on [GitHub Releases](https://github.com/ctucker1984/remember/releases).
