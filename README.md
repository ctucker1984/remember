# reMember

A WordPress plugin for membership-style communities: members, events, locations, applications, vetting, billing, and role-based access. It extends WordPress users with custom tables, an admin dashboard, and front-end templates (shortcodes and block patterns).

**Version:** 1.0.0  
**Requires:** WordPress 5.0 or higher  
**License:** GPL v2 or later

## Installation

1. Copy the `remember` folder into `wp-content/plugins/`.
2. In the WordPress admin, go to **Plugins → Installed Plugins** and activate **reMember**.
3. On activation, the plugin creates its database tables, seeds initial data, and assigns capabilities. The user who activates the plugin receives full reMember capabilities and a member record if one does not exist.
4. Complete the setup wizard or configure pages under **reMember → Settings** as needed.

## Requirements

- **PHP:** A current PHP version supported by your WordPress install (OpenSSL recommended if you use encrypted storage for integrations).
- **MySQL/MariaDB:** Standard WordPress database; custom tables are created on activation.
- **Permalinks:** Use pretty permalinks if you rely on front-end routes and shortcodes in a typical way.

## Features (overview)

| Area | Description |
|------|-------------|
| **Dashboard** | Summary and quick access for administrators. |
| **Members** | Member records tied to WordPress users; profiles and status. |
| **Events** | Events with locations, privacy, and dates. |
| **Applications** | Application workflow for joining or participating. |
| **Vetting** | Review and approval workflow for new members. |
| **Billing** | Payment-related admin tools (see code for current processors). |
| **Locations** | Venues and addresses used by events. |
| **Roles** | Custom capabilities and role management. |
| **Settings** | Plugin configuration; optional **QuickBooks Online** credentials and sync (OAuth details belong in Intuit’s developer console). |
| **Import / Export** | CSV templates and import/export for members, events, and locations (runs early on `admin_init` so file downloads send headers correctly). |
| **Front end** | Shortcodes and block patterns for member dashboard, events, applications, profile, etc. |

## Development

- **Main bootstrap:** `remember.php` → `includes/class-remember.php`
- **Admin UI:** `admin/class-remember-admin.php`, views under `admin/views/`
- **Public:** `public/class-remember-public.php`, partials under `public/partials/`
- **Data layer:** `includes/models/`, `includes/database/`

Activate `WP_DEBUG` and use your preferred logging while developing; the plugin includes a simple logger (`Remember_Logger`) for diagnostics.

## Contributing and support

Plugin URI and source references: see the plugin header in `remember.php`. For issues and changes, use your project’s GitHub workflow if applicable.

## License

This plugin is released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
