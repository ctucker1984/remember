# Xero Billing Integration Plan

**Status:** Phase 1 starting (credentials + OAuth + Settings shell)  
**Branch:** `v1.1.0`  
**Decision date:** 2026-08-05  
**Redirect URI (demo):** `https://remember.teamautomation.ddns.net/wp-admin/admin.php?page=remember-settings&tab=xero&xero_oauth_callback=1`

---

## Goals

- Add **Xero** as a billing provider that behaves **operationally like QuickBooks Online** (connect → map items → sync contact → create invoice on accept → sync payments/refunds into the billing register).
- Staff pick **ONE** active accounting provider per site: **QuickBooks** *or* **Xero** (not both).
- **Leave the existing QBO implementation intact** — do not refactor it behind an abstraction in this pass.
- Feed the **same billing matching / register display** (invoice #, amounts, status, payment/refund line JSON).

Non-goals for this pass:

- Provider interface / shared adapter layer (can extract later once Xero is proven).
- Running QBO and Xero simultaneously on one site.
- Changing how the member billing register UI is rendered (reuse current ledger logic).

---

## Architecture choice: Option A (parallel stack)

Mirror the QBO trio:

| QBO (keep as-is) | Xero (new) |
|------------------|------------|
| `Remember_QuickBooks_OAuth` | `Remember_Xero_OAuth` |
| `Remember_QuickBooks_API` | `Remember_Xero_API` |
| `Remember_QuickBooks_Sync` | `Remember_Xero_Sync` |

Settings, accept-application, cron, and Billing “Sync now” branch on the **active provider**.

Why not Option B now: QBO works; forcing an abstraction requires touching trusted code. Identical *ops* do not require identical *classes*.

---

## Current QBO behavior to mirror (reference)

Source of truth in code:

- `includes/integrations/class-remember-quickbooks-oauth.php`
- `includes/integrations/class-remember-quickbooks-api.php`
- `includes/integrations/class-remember-quickbooks-sync.php`
- Billing register build in `admin/views/members.php` (member detail)
- Shared table markup: `includes/utilities/class-remember-billing-template.php`

Operational loop:

1. OAuth connect → tokens in `remember_payment_processors.settings` (encrypted).
2. Map roles/products → QB items (`remember_qb_item_mappings`).
3. Member vetted / profile saved → Customer; store `remember_qb_customer_id`.
4. Application accepted → create Invoice; write `remember_payments` with `quickbooks_invoice_id` + DocNumber.
5. Sync → Payments / RefundReceipts linked to invoice → `amount_paid` / `amount_due` / status + `quickbooks_payment_lines` / `quickbooks_refund_lines` JSON.
6. Register UI expands those JSON lines into a chronological ledger.

Matching is **ID-based** (stored invoice id + LinkedTxn), not fuzzy DocNumber search.

---

## Xero entity mapping

| reMember concept | QuickBooks | Xero |
|------------------|------------|------|
| Member | Customer | Contact |
| Invoice on accept | Invoice | ACCREC Invoice |
| Payment applied | Payment | Payment (against Invoice) |
| Refund / credit | RefundReceipt | CreditNote (+ allocations) and/or payment reversals — confirm during API spike |
| Catalog line (role/product) | Item | Item |
| Org / company | realm_id | tenant_id (`Xero-tenant-id` header) |

OAuth2 notes (2026):

- Use **granular scopes** for new apps, e.g. `openid`, `profile`, `email`, `offline_access`, `accounting.contacts`, `accounting.invoices`, `accounting.payments`, `accounting.settings` (items) — exact list to finalize in Settings UI docs.
- After auth, call connections API and store chosen **tenant id**.
- Prefer JSON (`Accept: application/json`).

---

## One active provider

Add a site option, e.g. `remember_options['billing_provider']` = `none` | `quickbooks` | `xero`.

Rules:

- Only the active provider’s Connect / Sync / Create Invoice paths run.
- Switching provider shows a clear warning: existing external invoice IDs on payment rows remain for history but new invoices use the newly active provider; recommend finishing open cycles before switching.
- Manual processor remains for non-accounting / cash tracking if already used; do not auto-create Xero invoices when provider is `none` or mismatched.

---

## Data model plan

Prefer **parallel Xero columns** (leave `quickbooks_*` alone):

On `remember_payments` (migration, e.g. DB `1.16.0`):

- `xero_invoice_id` VARCHAR
- `xero_invoice_number` VARCHAR (InvoiceNumber)
- `xero_invoice_sort_ts` BIGINT UNSIGNED
- `xero_payment_lines` LONGTEXT (JSON — **same shape** as QB payment lines)
- `xero_refund_lines` LONGTEXT (JSON — **same shape** as QB refund lines)

User meta:

- `remember_xero_contact_id` (and sync token / updated date if needed)

Processors:

- Extend `processor_type` ENUM to include `'xero'` (or add row via seeder/migration).
- Seed inactive Xero processor like QBO.

Item mappings:

- Either extend `remember_qb_item_mappings` into a provider-neutral `remember_accounting_item_mappings` **or** add `remember_xero_item_mappings` parallel table. Prefer **parallel table** for this pass to avoid QBO risk: `entity_type`, `entity_id`, `xero_item_id`, `xero_item_name`, timestamps.

Billing register / Billing table:

- When reading a payment row, use active provider (or whichever external ids are populated) to pick invoice # and line JSON. Prefer: if `billing_provider === xero` use `xero_*`; if quickbooks use `quickbooks_*`. Fallback for historical rows if only one set is filled.

Normalized line JSON (target shape for UI — match existing QB consumer):

```json
{
  "amount": 0,
  "txn_date": "Y-m-d H:i:s",
  "payment_method": "…",
  "qb_payment_id": "…",
  "doc_number": "…",
  "sort_ts": 0
}
```

For Xero, reuse the same keys where possible (`qb_payment_id` can stay as generic `external_payment_id` **only if** register code is updated once to read a neutral key; otherwise map Xero ids into the existing key names temporarily to avoid UI churn). **Decision for implementer:** either (1) one small UI rename to `external_*` keys used by both, or (2) Xero sync writes the existing key names. Prefer (1) if touch is small; else (2).

---

## Files to add / touch

**Add**

- `includes/integrations/class-remember-xero-oauth.php`
- `includes/integrations/class-remember-xero-api.php`
- `includes/integrations/class-remember-xero-sync.php`
- Optionally `includes/models/class-remember-xero-item-mapping.php`

**Touch**

- `includes/database/class-remember-database.php` + `class-remember-database-updater.php` (schema)
- `includes/database/class-remember-seeder.php` (xero processor)
- `admin/views/settings.php` (provider picker + Xero tab/section)
- `admin/class-remember-admin.php` (OAuth callback, sync actions)
- `includes/class-remember.php` (cron / vetted / profile-saved hooks → dispatch by provider)
- `admin/views/applications.php` (create invoice on accept)
- `admin/views/billing.php` + member detail register (read provider columns)
- `admin/views/members.php` (manual “Sync to Xero” when active)
- `README.md` changelog when shipping

**Do not refactor** QBO classes beyond a thin dispatch “if active provider is qb …” at call sites.

---

## Settings UX

1. **Billing provider** radio: None / QuickBooks Online / Xero.
2. When Xero selected, show:
   - Client ID / Client Secret / environment (if applicable)
   - Redirect URI help text
   - Connect / Disconnect
   - Connected org (tenant) name
   - Item mapping UI (roles + products → Xero Items)
   - Sync interval / Sync now (shared pattern with QBO)
3. When QBO selected, existing QB UI unchanged.

Redirect URI pattern (mirror QB):  
`admin.php?page=remember-settings&tab=…&xero_oauth_callback=1`

---

## Sync parity checklist

| Behavior | QBO today | Xero target |
|----------|-----------|-------------|
| Connect OAuth | ✓ | ✓ |
| Encrypt secrets | ✓ | ✓ |
| Contact/Customer by email then create | ✓ | ✓ |
| Create invoice on accept | ✓ | ✓ |
| Store external invoice id + number | ✓ | ✓ |
| Cron + Billing page sync | ✓ | ✓ |
| Payment lines JSON → register | ✓ | ✓ |
| Refund lines JSON → register | ✓ | ✓ |
| Amount/status rollup | ✓ | ✓ |
| Item auto-match by name + manual map | ✓ | ✓ |
| Manual member sync button | ✓ | ✓ |

---

## Implementation phases

### Phase 0 — Spike (short)

- Register Xero app; confirm OAuth + tenant selection.
- Manually create Contact, Invoice, Payment in sandbox; note field names for InvoiceNumber, Payment allocations, CreditNotes.

### Phase 1 — Plumbing

- DB migration + seeder.
- OAuth connect/disconnect + provider option.
- Settings UI shell (no invoice create yet).

### Phase 2 — Write path

- Contact sync (vetted / profile saved / manual).
- Item mapping.
- `create_invoice_for_application` for Xero.
- Wire accept-application dispatch.

### Phase 3 — Read / match path

- `sync_payment_status` for Xero (payments + refunds/credit notes).
- Cron + Billing sync + register display.
- End-to-end test against sandbox.

### Phase 4 — Docs / polish

- README changelog + short Settings help text.
- Switching-provider warning copy.
- Logging via `Remember_Logger` consistent with QBO.

---

## Risks / open questions

1. **Refunds:** Exact Xero equivalent of RefundReceipt (CreditNote allocation vs voided payment) — resolve in Phase 0.
2. **Tax:** reMember stays subtotal-oriented; Xero line tax settings should mirror QBO’s “don’t invent tax in WP” approach.
3. **Historical QBO rows** after switching to Xero: show old QB invoice #s when `quickbooks_*` present; new rows use `xero_*` only.
4. **Rate limits:** Xero per-tenant limits — batch sync carefully; reuse “last_sync_at + interval” gate.

---

## Doc housekeeping (this commit)

| File | Action |
|------|--------|
| `XERO_PLAN.md` | **Added** — this plan |
| `README.md` | Point to this plan; note Xero as planned in 1.0.1 changelog |
| `PROGRESS.md` | **Removed** — early build checklist; superseded by README changelog |
| `SHORTCODE_STRATEGY.md` | **Removed** — early shortcode planning; live docs live in Settings |
| `ARCHITECTURE.md` | **Kept** — schema reference (partially stale vs code; update when Xero ships) |
| `TESTING.md` | **Kept** — still useful smoke-test outline |

---

## Next session starter

1. Confirm Xero app credentials available (or stub Settings for later paste).
2. Phase 0 spike against Xero sandbox.
3. Implement Phase 1 on `v1.0.1` (or `feature/xero-billing`).
