# Printable admission ticket + receipt (post–1.1.2)

**Status:** Held — ship **1.1.2** first, then implement (likely **1.1.3** or **1.2.0**).

**Decided:** 2026-08-07

## Product decisions (locked)

1. **Adverse stamps only**
   - No “VALID / PAID” stamp.
   - **PAYMENT REQUIRED** when not fully paid (`pending` / `partial`, or equivalent from billing sync).
   - **VOID** when manually voided (define void path when building).

2. **High-touch email**
   - On accept: email that ticket is ready (may include balance due).
   - On fully paid: email with paid ticket.
   - Balance-due mail must support **admin blast** later (all accepted applicants who still owe).

3. **Payment truth**
   - Rely on billing provider status as shown via QBO / Xero sync (`payment_status` on the payment row).
   - Do not invent a separate “$0 = paid” rule; if provider/sync says paid (including zero-balance invoices), ticket reflects that.

4. **Branding**
   - Default logo = WordPress **Site Logo** (Customizer / FSE `custom_logo`).
   - reMember Settings: optional **logo override** for tickets (and possibly related emails).

5. **Delivery model**
   - Live HTML ticket (derived from application + event + payment), print CSS / `window.print`.
   - Member front end: print in any ticket-eligible status.
   - Admin: view / print / download from application (and related) screens.
   - PDF library optional follow-up; not required for first ship.

## Placement (planned)

- Member: dashboard accepted application detail (+ optional My Events).
- Admin: application detail (+ list action; blast from applications/billing later).
- Emails: wire notification sender; templates for accept/ticket-ready and paid; blast for balance due.

## Out of scope for 1.1.2

Do not implement on the `v1.1.2` branch. Track here until a dedicated release branch is cut.
