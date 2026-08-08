# reMember 1.3.0 — TODO

**Branch:** `v1.3.0`  
**From roadmap:** Questionnaires / waivers (E–F). See `TODO_ROADMAP_POST_DEMO.md`.

## Upgrade safety

- [x] Deactivate → replace → silent reactivate on zip **upload overwrite**
- [x] **Note for sites still on ≤1.2.0:** deactivate reMember once before first install of 1.3.0 (called out in 1.3.0 release notes).

## E — Profile custom fields (questionnaires)

**Scope:** Member profile only (no per-event questions).

- [x] Schema `1.23.0`: `remember_profile_questions` + `remember_profile_question_responses`
- [x] Admin → **Custom Fields**: question text, export field key, type (text | dropdown with key|label options), required, active, sort
- [x] Show on registration, front profile edit/view, admin member edit/detail
- [x] Member CSV export/import columns = export field keys (select stores/exports option **key**; multiselect exports `key|key`)

## F — Event agreements

**Locked:** Library named **Agreements** (custom tables, not CPT). Events pin **specific revisions**. Accept on **application submit** — checkbox + typed legal name **per agreement**. Events with none attached skip the step.

- [x] Schema `1.24.0`: `agreements`, `agreement_revisions`, `event_agreements`, `agreement_acceptances`
- [x] Admin Settings flyout → Agreements (CRUD + publish new revision)
- [x] Admin event edit: attach multiple agreements at pinned revisions
- [x] Front apply: show pinned revisions; require ack + legal name each; block submit
- [x] Admin application detail: acceptance evidence + revision body

## Follow-ons on `v1.3.0` (with E/F)

- [x] Schema `1.25.0`: `superseded_at` — admin **Allow reapply** for declined/cancelled (keeps history; member can re-ack agreements)
- [x] Member dashboard cancel: leave invoice alone (no void/refund radios); admin can **unwind billing** later
- [x] Admin member edit: only nickname, display name, legal first/last, cell phone required (address/IM/emergency/custom optional)
- [x] Cap `remember_event_data_export` (Event Admin + System Admin); event detail **Export accepted participants** CSV (profile + custom short names + role + add-on qty columns). Schema bump `1.26.0` (cap grant)
- [x] Cap `remember_import_export` (System Admin by default; assignable) gates Settings → Import/Export
- [x] Richer member CSV (nickname, sizes, dietary/allergies/medical, timezone from user meta) + Custom Fields definition import/export. Schema `1.27.0`
