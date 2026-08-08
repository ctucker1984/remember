# reMember 1.2.0 — TODO

**Branch:** `v1.2.0`  
**Includes:** Admission tickets + roadmap slices **A–D**. Slices **E–F** → 1.3.0.

## Tickets (prior)

- [x] HTML ticket + stamps + member/admin print + emails + Email Ticket button
- See `TODO_TICKETS.md`

## A — Polish

- [x] Slash-escaping / `wp_unslash` on admin text saves
- [x] Rich text (`wp_editor`) for location details + event public description + member Interests (admin + front)
- [x] Timezone: single typeahead combobox (type “Chicago” in the field; no separate filter)
- [x] Emergency contact phone required (admin edit + server check)
- [x] Admin Declined vs Member Cancelled; invoice prompt void / refund / leave; ticket VOID

## B — Profile / registration

**Required (registration + profile):** Nickname; Legal First/Last; Street; City; State/Province; Postal Code; Cell Phone; Time Zone; Instant Messenger; Emergency First/Last/Phone/Relationship.

- [x] Clothing sizes: shirt/pants S–6XL, shoes 6–15 (schema `1.19.0` / `1.21.0` seed)
- [x] Full registration profile + shared required-field policy (register / front profile / admin)

## C — Events

- [x] Add-on max qty per event × event-role (0 = hide); schema `1.20.0`
- [ ] Attendee-only event details field + front-end gate

## D — Billing

- [ ] Auto-email invoice from Xero/QBO on accept
- [ ] Member ledger chronological order + Xero credit-note accounting

## Docs

- [x] `TODO_ROADMAP_POST_DEMO.md` — A–D in 1.2.0, E–F in 1.3.0
