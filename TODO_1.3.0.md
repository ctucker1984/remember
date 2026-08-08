# reMember 1.3.0 — TODO

**Branch:** `v1.3.0`  
**From roadmap:** Questionnaires / waivers (E–F). See `TODO_ROADMAP_POST_DEMO.md`.

## Upgrade safety

- [x] Deactivate → replace → silent reactivate on zip **upload overwrite**
- [ ] **Note for sites still on ≤1.2.0:** deactivate reMember once before first install of 1.3.0 (or ship a 1.2.1 hotfix with only the upgrader).

## E — Profile custom fields (questionnaires)

**Scope:** Member profile only (no per-event questions).

- [x] Schema `1.23.0`: `remember_profile_questions` + `remember_profile_question_responses`
- [x] Admin → **Custom Fields**: question text, export field key, type (text | dropdown with key|label options), required, active, sort
- [x] Show on registration, front profile edit/view, admin member edit/detail
- [x] Member CSV export/import columns = export field keys (select stores/exports option **key**; multiselect exports `key|key`)

## F — Event waivers

- [ ] Deferred until E is validated
