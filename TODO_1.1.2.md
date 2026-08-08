# reMember 1.1.2 — TODO

## Photo upload: restore zoom + pan

**Reported:** 2026-08-06 — zoom and drag-to-recenter appear missing on photo upload during **registration** (and possibly profile upload).

**Expected (as in 1.0.1 / README):** circular live preview with **zoom** and **drag-to-recenter**; crop applied on save.

**Investigate:**
- [x] Registration photo UI vs profile photo UI (`public/partials/`, `assets/js/public.js`) — registration had **no** photo UI; cropper lived only on profile.
- [x] Confirm whether controls are absent, CSS-hidden, or JS not binding on the register form — absent markup; JS only bound `#photo_file` inside `.remember-profile-photo-edit`.
- [x] Restore parity with profile upload behavior — optional photo on register with same cropper (zoom + drag); JS generalized per wrap; form `enctype` + server upload after account create.
- [x] Admin Members → Edit Profile — same cropper (was plain file input).
- [x] Smoke-test register + front-end profile edit + admin member edit on demo — skipped by request; ship without.

**Branch:** `v1.1.2` (shipped)

---

## Deferred (completed on v1.2.0)

Printable admission ticket + receipt — see `TODO_TICKETS.md` / branch `v1.2.0`.
