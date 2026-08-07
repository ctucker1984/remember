# reMember 1.1.2 — TODO

## Photo upload: restore zoom + pan

**Reported:** 2026-08-06 — zoom and drag-to-recenter appear missing on photo upload during **registration** (and possibly profile upload).

**Expected (as in 1.0.1 / README):** circular live preview with **zoom** and **drag-to-recenter**; crop applied on save.

**Investigate:**
- [ ] Registration photo UI vs profile photo UI (`public/partials/`, `assets/js/public.js`)
- [ ] Confirm whether controls are absent, CSS-hidden, or JS not binding on the register form
- [ ] Restore parity with profile upload behavior
- [ ] Smoke-test register + front-end profile edit on demo

**Branch:** `v1.1.2`
