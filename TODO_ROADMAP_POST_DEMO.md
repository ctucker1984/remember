# Post-demo roadmap (business meeting notes)

**Captured:** 2026-08-07  
**Status:** A–D shipped in **1.2.0**. **E** (custom fields) and **F** (agreements) shipped in **1.3.0**.  
**Source:** Live demo feedback.

---

## Release assignment

| Slice | Theme | Target |
|-------|--------|--------|
| Tickets (prior) | Admission ticket + emails | **1.2.0** (in progress) |
| **A** | Escaping/rich text, emergency phone, eject, timezone UX | **1.2.0** |
| **B** | Profile sizes + full registration | **1.2.0** |
| **C** | Add-on qty limits + attendee-only event details | **1.2.0** |
| **D** | Auto-email invoice; ledger order + Xero credit notes | **1.2.0** |
| **E** | Questionnaires (custom fields) | **1.3.0** |
| **F** | Event waivers | **1.3.0** |

---

## 1. Clothing sizes on profile (shirt / pants / shoes)

**Ask:** American male sizes in dropdowns.

**Locked:** Shirt/pants S–6XL; shoes US 6–15 (whole). Optional on profile.

**Done:** `remember_clothing_size_options` + profile columns (`1.19.0`); 5XL/6XL seed (`1.21.0`); admin/front/register dropdowns.

---

## 2. Event add-on: qty per event-role

**Locked:** Per add-on × event-role max qty. `0` = hide from that role. Example: Inmate uniform/GPS = 1 for Inmate, 0 for Guard; Guard polo = 2 for Guard, 0 for Inmate.

**Done (schema `1.20.0`):** `remember_event_merchandise_role_limits`; admin event edit matrix; apply/dashboard enforce + AJAX filter by selected role. Legacy `event_merchandise.max_quantity` kept as fallback when no limit row exists.

---

## 3. Front-end registration = full profile (+ required/optional)

**Today:** Register is minimal (username, display/legal name, email, cell, timezone, optional photo, password). Full profile is later.

**Done:** `[remember_register]` is a full profile form; `Remember_Profile_Fields` drives requireds for register, front profile, and admin edit.

**Required:** Nickname; Legal First/Last; Street; City; State/Province; Postal Code; Cell Phone; Time Zone; Instant Messenger; Emergency First/Last/Phone/Relationship. Other fields optional.

**Timezone UX:** Single combobox — type in the field (e.g. “Chicago”); no separate filter box.

---

## 4. Escape characters before apostrophes + rich text

**Done (1.2.0 / 1.3.0):** Big-text fields use `wp_editor` + `wp_kses_post( wp_unslash( … ) )` (location details, event description, attendee details, interests, agreement bodies). New saves no longer store literal `\'`. Optional: one-time cleanup of old bad rows; admin location detail should render with `wp_kses_post` (not `esc_html`) if still showing tags literally.

---

## 5. Admin member edit — emergency phone required

**Done (1.3.0):** Admin edit uses a lean required set — nickname, display name, legal first/last, cell phone only. Address, timezone, IM, emergency contact, and custom fields are optional for staff. Front/register keep the full required list from item 3.

---

## 6. Billing provider auto-sends invoice on accept

**Done (1.2.0):** After successful invoice create on accept (and reprocess billing), call Xero/QBO email-invoice APIs. Soft-fail with admin notice. Settings → “Email invoice on accept” (default on).

---

## 7. Bug: member billing ledger order + Xero credit notes

**Done (1.2.0):** Sort keys prefer document `Date` / `TxnDate` over sync timestamps. Xero credit-note allocations credit the ledger and reduce running balance; QBO refund receipts remain audit-only (do not inflate balance due).

---

## 8. Reject / eject application at any stage (including fully paid)

**Decided / implemented (1.2.0)**
- **Admin → Declined** (`declined`) at pending / waitlisted / accepted (incl. paid).
- **Member → Cancelled** (`cancelled`) from accepted dashboard withdraw.
- Both prompt: **Void invoice** / **Refund (credit note)** / **Leave invoice unaltered**.
- Both mark admission ticket **VOID**.
- Xero: void + credit-note allocate supported; QBO: void supported; paid refund in QBO may need manual finish + sync.

---

## 9. Attendee-only event details

**Done (schema `1.22.0`):** `attendee_details` rich text on events; admin Public description + Attendee-only details editors; front event detail shows attendee block only when the member has an `accepted` application. Existing `event_description` stays public (no content migration).

---

## 10. Questionnaires (custom fields) — profile only

**Done (1.3.0 / schema `1.23.0`):** Admin → Custom Fields. Each question has label, export `field_key` (CSV header), type `text` or `select` (options as `key|Label`), required/active/sort. Shown on register + profile + admin member. CSV export/import appends field keys. No per-event questionnaires; no conditional logic.

---

## 11. Event agreements (was: waivers)

**Done (1.3.0 / schema `1.24.0`):** **Agreements** library (custom tables). Immutable revisions; events pin revision(s); apply requires checkbox + typed legal name per pinned agreement; acceptances store IP/UA/`accepted_at` + revision link. No CPT. DocuSign later.

---

## Cross-cutting

- **Schema:** expect multiple `remember_db_version` bumps; keep seeder/updater patterns.
- **Notifications:** new types for eject, possibly invoice-sent confirmation (provider still sends the invoice email).
- **1.2.0 tickets:** finish/ship tickets first; this roadmap is **post–1.2.0** unless you pull a hot item forward.
- **Demo SMTP:** unrelated to product; keep WP Mail SMTP for real sends while testing mail features.

---

## What’s left from this meeting

| Item | Status | Notes |
|------|--------|--------|
| 1 Sizes | Done (1.2.0) | |
| 2 Add-on role limits | Done (1.2.0) | |
| 3 Full registration + requireds | Done (1.2.0) | Admin lean requireds in 1.3.0 (item 5) |
| 4 Escape / rich text | Done | Rich-text saves with `wp_unslash`; optional legacy cleanup |
| 5 Admin emergency phone | Done (1.3.0) | Lean admin requireds |
| 6–9 Billing / eject / attendee details | Done (1.2.0) | |
| 10 Custom fields | Done (1.3.0) | + multiselect |
| 11 Agreements | Done (1.3.0) | + reapply / cancel billing UX |

**Likely next product work:** ideas beyond this doc (DocuSign, per-event questionnaires, notifications polish).
