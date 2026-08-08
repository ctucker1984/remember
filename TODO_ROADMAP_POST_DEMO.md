# Post-demo roadmap (business meeting notes)

**Captured:** 2026-08-07  
**Status:** A–D shipped in **1.2.0**. **E** (profile custom fields) on `v1.3.0`. F (waivers) still open.  
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

**Likely cause:** `sanitize_textarea_field( $_POST[...] )` without `wp_unslash()` on some admin saves (e.g. location details, some member fields). Stored `\'` then shown literally.

**Plan**
- Audit all textarea/content saves: `wp_unslash` → sanitize appropriately.
- One-time cleanup helper or migration note for existing bad rows (optional SQL/stripslashes pass).
- Promote key “big text” fields to **rich text** (`wp_editor` / block-friendly HTML):
  - Candidates: location `details`, event `event_description`, future **waivers**, maybe member `interests` (or keep interests plain).
- Output with `wp_kses_post` (not `esc_html` of raw HTML).
- Keep application notes / short fields plain unless you want those rich too.

**Open questions**
- Which fields must be rich text in v1 vs plain + slash-fix only?

---

## 5. Admin member edit — emergency phone required

**Finding:** Front/admin emergency phone is largely optional; cell is the required phone. If you’re blocked on save, likely a browser `required` attribute or custom JS on that field — worth re-checking when we touch member-edit.

**Plan**
- Align validation with registration policy: if emergency phone is required at register, keep required on admin/profile; if optional, remove blocking `required`.
- Don’t invent a new rule until registration required-list is set (item 3).

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

## 11. Event waivers — prepare to support

**Today:** None.

**Plan (slice F)**
- Admin-defined waiver per event (or global template + event override): rich text body, version/timestamp.
- Member affirmation **internal v1**:
  - Checkbox + typed legal name + IP + user agent + `accepted_at` stored on application (or `remember_waiver_acceptances`).
  - Block accept? or block check-in? — typically **must accept before application submit** or before ticket valid.
- DocuSign / similar: later integration spike; don’t block internal flow.

**Open questions**
- Must waive before apply, after accept, or before ticket “valid”?
- One waiver per event vs reusable library of waiver templates?

---

## Cross-cutting

- **Schema:** expect multiple `remember_db_version` bumps; keep seeder/updater patterns.
- **Notifications:** new types for eject, possibly invoice-sent confirmation (provider still sends the invoice email).
- **1.2.0 tickets:** finish/ship tickets first; this roadmap is **post–1.2.0** unless you pull a hot item forward.
- **Demo SMTP:** unrelated to product; keep WP Mail SMTP for real sends while testing mail features.

---

## When you return — decide these first

1. **Registration required vs optional list** (item 3).  
2. Add-on limits: **per application only** vs **per role matrix** now (item 2).  
3. Eject: status naming + **billing void default** (item 8).  
4. Rich-text field list (item 4).  
5. Size enums / male-only (item 1).  
6. Ledger bug: **example member / invoice** on demo (item 7).  
7. Priority order if not A→F above.

No implementation started from this meeting doc until you pick slice(s) and answer blockers.
