# Printable admission ticket + receipt (v1.2.0)

**Status:** In progress on branch `v1.2.0`.

**Decided:** 2026-08-07

## Product decisions (locked)

1. **Adverse stamps only**
   - No “VALID / PAID” stamp.
   - **PAYMENT REQUIRED** when not fully paid (`pending` / `partial`, or no payment row yet).
   - **VOID** when admin voids the ticket (`ticket_voided`) or payment is `cancelled` / `refunded`.

2. **High-touch email**
   - On accept: `event_application_accepted` (ticket link + balance context).
   - On fully paid: `event_ticket_paid`.
   - Balance-due: `payment_due_reminder` (single + Applications list blast).

3. **Payment truth**
   - Relies on synced `payment_status` from QBO / Xero (or empty = unpaid).

4. **Branding**
   - Default: WordPress Site Logo.
   - Override: Settings → General → Ticket logo override.

5. **Delivery model**
   - Live HTML ticket via `?remember_ticket=` (nonce + capability/ownership).
   - Member dashboard + admin application detail print/download/email.
   - PDF library optional follow-up (not required for first ship; HTML print is stable).
