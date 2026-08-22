# Changelog

All notable changes to reMember are listed here. The current plugin version is in `remember.php` (`REMEMBER_VERSION`) and [GitHub Releases](https://github.com/ctucker1984/remember/releases).

## 1.3.8

- **Enhancement:** Clothing sizes keep the member’s actual size and show the inventory size that is available. Settings → Clothing has a write-in Stock table per category; **Available as** is filled from that, not the seeded body-size list. Dropdowns and staff views use labels like `XL (available L)`. Quantity is a later table related to Stock. Database 1.41.0. Closes [#31](https://github.com/ctucker1984/remember/issues/31).
- **Fix:** Hide the Bluehost plugin’s “Login with Bluehost” button on `wp-login.php` so members use username and password. Does not replace WordPress login or block Bluehost Account Manager → Log in to WordPress. Filter `remember_hide_hosting_sso` to keep the button.
- **Enhancement:** Nonced front-end Log out via Appearance → Menus (reMember box), a **Log out** block in the Site Editor Navigation, a Custom Link to `/remember-logout`, or `[remember_logout]`. The admin bar is hidden when the only WordPress role is Subscriber; other WP roles still see it. reMember roles do not affect the bar.
- **Enhancement:** Members can change their password on Edit Profile (current, new, confirm under Basic Information) with Save Profile. Leave the fields blank to keep the current password. Closes [#27](https://github.com/ctucker1984/remember/issues/27).
- **Security:** Event apply builds add-on names/descriptions and role labels as text, not HTML, so a product name cannot run markup in the member's browser. The dashboard already escaped these in PHP.
- **Security:** CSV exports prefix cells that start with `=`, `+`, `-`, or `@` so Excel/LibreOffice will not treat member-controlled text as a formula. Re-import strips that prefix so phones and IM handles round-trip.
- **Security:** Browser security headers on front, login, admin, and REST: `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, HSTS on HTTPS, and a WordPress-compatible Content-Security-Policy. Filter `remember_security_headers` to tighten or disable. Does not replace headers the host already sends.
- **Security:** Unauthenticated `GET /wp-json/wp/v2/users` no longer returns login slugs. Editors/admins still can (block editor); everyone else gets 401.
- **Security:** XML-RPC stays available for Jetpack and the WordPress mobile app (`system.multicall` included). Pingback methods, the X-Pingback header, and open pings are removed (SSRF). Multicall brute-force is a server concern (fail2ban / rate-limit / WAF), not a plugin 403.
- **Security:** Debug logs no longer write to the public `/wp-content/debug.log` URL. reMember stores them in a web-blocked uploads directory, Apache/LiteSpeed deny HTTP GET of `debug.log`, and PHP’s error_log is redirected there when `WP_DEBUG_LOG` is the boolean default.
- **Enhancement:** Interests is limited to 2,000 characters (plain text) on registration, profile edit, and admin member edit, with a live counter. Closes [#29](https://github.com/ctucker1984/remember/issues/29).

## 1.3.7

- **Enhancement:** The event card printout uses a larger square photo at top left, with the display name and the first four opted-in custom fields in the column beside it. Remaining event-card fields continue in two columns under that row, then Interests.
- **Enhancement:** Assigned member numbers print on both sheets: under the display name on the confidential profile and the event card, and in the confidential footer.
- **Fix:** Member billing register follows Xero/QBO invoice totals: voided or deleted invoices are cancelled locally (zero balance, no ghost debit), deleted payments and credit notes drop off on sync, and remaining credits (Xero AmountCredited / QBO credit memos) are applied so Current Balance matches the provider.
- **Fix:** Opening a member profile always refreshes invoices, payments, and credits from Xero or QuickBooks (no 60-second cache).
- **Fix:** Settings → Xero shows **Reconnect Xero** while a connection is stored, and warns when the access token can no longer be refreshed. Previously only Disconnect was offered, so an expired token looked connected with no way to authorize again.
- **Fix:** Xero reconnect keeps the authorization error on the Xero tab (it was a 60-second flash notice), uses a stored OAuth state plus PKCE, and exchanges the code with the same redirect URI that started the flow so a completed reconnect actually replaces the tokens.
- **Fix:** Xero token exchange, refresh, and revoke send the same User-Agent as other Xero calls. identity.xero.com was returning HTTP 403 (Akamai) for WordPress’s default user-agent, which blocked reconnect and left the stored tokens expired. A failed reconnect notice is cleared once the live connection works again.

## 1.3.6

- **Enhancement:** Member profiles print in two formats, chosen from a **Print** menu on the profile. *Confidential profile* is the full staff record — identity, profile, emergency contact, and health on page one, interests and custom fields on page two — with red CONFIDENTIAL banners repeated on every page. *Event card* is meant for posting at an event: photo, display name, interests, and opted-in custom fields, with no contact details, address, roles, emergency contact, or health information. wp-admin chrome, action buttons, vetting cases, and the billing register stay off both. Save-as-PDF filenames use `{display_name}_member_full_profile_{yymmdd}.pdf` and `{display_name}_event_card_{yymmdd}.pdf`.
- **Enhancement:** Custom profile fields gain a **Show on event card** setting on the Custom Profile Fields screen, off by default, so a question like "What medications do you take?" never reaches a publicly posted card unless you opt it in. The flag round-trips through the custom fields CSV. Database 1.35.0.
- **Security:** Emergency contact and health information (dietary, allergies, medical) are gated behind `remember_access_emergency_contact` and `remember_access_health` (shown as Access Emergency Contact / Access Health Information). Without those caps, the fields are hidden on member view/edit, omitted from member and event CSV exports, and ignored on import and forged POSTs. Default grants: System Administrator and Vetting get both; Event Administrator gets health only (for event planning). Database 1.36.0; renamed from `remember_read_*` in 1.37.0.
- **Enhancement:** Role capability editing uses a module × Create/Read/Edit/Delete matrix, with settings, exports, emergency contact, and health listed under Other.
- **Security:** The member profile's billing register honors `remember_read_billing`. Staff without it (Vetting, for example) saw the full invoice, payment, and refund ledger with the running balance, even though the Billing screen was already hidden from them; the ledger is no longer built or rendered for them, and the QuickBooks/Xero sync buttons and their POST handlers now also require `remember_update_billing`. Vetting cases on the profile likewise require `remember_read_vetting`.
- **Security:** Privilege escalation hardening — role capability saves require `remember_update_roles` (the bare `update_capabilities` path had none), you can only grant caps you already hold, System Administrator cannot be deleted or edited except by a WordPress administrator, and member role assignment cannot add or remove roles whose caps you do not hold. Application billing mutations (invoice create on accept, reprocess, unwind/void) and payment UI require billing caps. Member CSV export respects attendees-only scoping. Mutation UI (Add Member, Accept/Decline, etc.) is hidden when the matching cap is missing.
- **Fix:** "None" sorts first in dietary, allergy, and medical accommodation checklists (profile, registration, and admin edit). Database 1.38.0.
- **Fix:** The member profile's top row sizes itself to the cards the viewer may see. Staff without emergency contact or health access no longer get a third-width profile card with empty space beside it.
- **Fix:** The confidential printout no longer strands the dietary/allergy/medical cards below the profile card with a dead gap after Emergency Contact. Emergency contact and health now share one column that stacks tightly beside the profile (the old layout leaned on a grid row span that collapses without explicit rows). The event card is unaffected.
- **Security:** Member profile printing is gated by `remember_print_confidential` and `remember_print_event_card` (Print Confidential Profile / Print Event Card). Without the matching cap the Print control is hidden, Ctrl/Cmd+P yields a permission notice instead of the sheet, and each format is offered only when allowed. Seeded to System Administrator and Event Administrator (not Vetting). Database 1.39.0.
- **Fix:** Settings → Plugin Version reports the installed files (`REMEMBER_VERSION`), and the stored `remember_version` option is synced on admin load after Upload → Replace. Silent reactivation was leaving the option on the previous release.

## 1.3.5

- **Enhancement:** Updates arrive in the WordPress dashboard. reMember checks GitHub Releases and offers the packaged `remember-x.y.z.zip`, so Plugins → Update works like any other plugin (requires WordPress 5.8+).
- **Fix:** Every rich-text field is Visual-only — the Visual/Code switcher is gone from events, locations, agreements, member edit, registration, and profile.

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
