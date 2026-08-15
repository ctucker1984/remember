# Changelog

All notable changes to reMember are listed here. The current plugin version is in `remember.php` (`REMEMBER_VERSION`) and [GitHub Releases](https://github.com/ctucker1984/remember/releases).

## 1.3.4

- **Enhancement:** Admin-assignable unique alphanumeric member number; members see it read-only. Closes [#21](https://github.com/ctucker1984/remember/issues/21).
- **Enhancement:** Event apply and dashboard application edits require profile-currency confirmation (phrase + profile saved within 24 hours); profile `updated_by` audit fields. Closes [#22](https://github.com/ctucker1984/remember/issues/22).
- **Fix:** Interests editors (profile, registration, admin) show Visual only — Code tab hidden.
- **Fix:** Long profile edit forms no longer clip custom fields or force horizontal overflow.
- **Fix:** Upload → Replace no longer fails on git/dev trees (`.git`, `dist`, etc.): deactivate earlier, purge non-release paths, chmod writable, retry clear when WordPress reports `files_not_writable`.

## 1.3.3

- **Security:** Attendees-only staff can no longer open arbitrary member profiles via `?view=` ID; detail and POST actions use the same shared-event scope as the list.
- **Security:** Setup wizard, Settings, and Products mutations re-check `remember_access_settings` (not nonce alone).
- **Fix:** Applications-only staff can open the admin dashboard (menu and page caps aligned).
- **Fix:** Event-role AJAX uses separate admin/front nonces; no longer trusts Referer or `manage_options` for the full role list.
- **Fix:** Staff ticket viewing uses `remember_read_applications` (replaced undefined `remember_view_applications`).
- **Enhancement:** Admin-managed Instant Messenger platforms (DB 1.32.0); Social Media + IM combined in a two-column **Platforms** settings tab. Closes [#20](https://github.com/ctucker1984/remember/issues/20).

## 1.3.2

- **Fix:** Country is required on registration and profile (defaults to US). Closes [#17](https://github.com/ctucker1984/remember/issues/17).
- **Enhancement:** Interests prompt asks what the member wants from the event. Closes [#16](https://github.com/ctucker1984/remember/issues/16).
- **Fix:** Time zone starts empty at registration and opens unfiltered; clearer importance copy. Closes [#13](https://github.com/ctucker1984/remember/issues/13).
- **Enhancement:** Dietary, medical, and allergy catalogs include None and require a response. Closes [#15](https://github.com/ctucker1984/remember/issues/15).
- **Enhancement:** Expanded allergies seed list (incl. pomegranate, grapefruit). Closes [#19](https://github.com/ctucker1984/remember/issues/19).
- **Enhancement:** Expanded dietary restrictions and medical accommodations for event planning coverage.
- **Enhancement:** Privacy copy explains sharing and encourages photo + IM for networking. Closes [#18](https://github.com/ctucker1984/remember/issues/18).
- **Enhancement:** Profile photo required at registration. Closes [#14](https://github.com/ctucker1984/remember/issues/14).
- **Enhancement:** Custom profile fields can be required only when an earlier pick-one / pick-several field matches chosen values. Closes [#11](https://github.com/ctucker1984/remember/issues/11).
- **Fix:** Load `dbDelta` before admin schema migrations so upgrades are not stalled mid-chain.
- **Fix:** Admin mobile — vetting, applications, billing, and related tables stack into labeled cards. Closes [#8](https://github.com/ctucker1984/remember/issues/8), [#9](https://github.com/ctucker1984/remember/issues/9), [#10](https://github.com/ctucker1984/remember/issues/10).
- **Chore:** Plugin header `Update URI` points at the GitHub repository.

## 1.3.1

- **Fix:** Custom fields on registration/profile no longer collide with the two-column register layout. Closes [#6](https://github.com/ctucker1984/remember/issues/6).
- **Fix:** Admin mobile layout — member/event headers stack; wide tables scroll on small screens. Closes [#7](https://github.com/ctucker1984/remember/issues/7).
- **Docs:** Slim root README; remove planning markdown from the release tree.

## 1.3.0

- Upload → Replace safely deactivates/reactivates the plugin during zip upgrades.
- Custom profile fields (text / single / multi-select); registration and profile.
- Agreements library with pinned event revisions and typed legal-name acknowledgement on apply.
- Allow reapply for declined/cancelled applications.
- Leaner admin member-edit required fields.
- Event participant CSV export (accepted applications).
- Import/Export capability; richer member CSV; custom fields definition import/export.

## 1.2.0

- Printable admission tickets and receipts; email on accept / paid / balance due.
- Rich text for locations, event description, Interests; registration/profile polish; per–role add-on max qty.
- Billing: email Xero/QBO invoice on accept; credit-note sync; release zip packaging (`remember/` root folder).

## 1.1.x

- Registration and admin photo cropper; Xero customer invoice links on the member register; safer payment sync; release packaging fixes.

## 1.1.0

- Billing provider selector (none / QuickBooks / Xero); full Xero path parallel to QuickBooks.

## 1.0.x

- Baseline: members, events, applications, vetting, QBO billing, roles, locations, products, shortcodes; display-name / privacy / convert-WP-user refinements in 1.0.1.
