# reMember Plugin - Shortcode Strategy

## Proposed Frontend Pages & Shortcodes

Based on the plugin functionality, we'll need the following frontend pages with shortcodes:

### Required Pages

1. **Member Registration** (`[remember_registration]`)
   - New member signup form
   - Profile completion (legal name, address, phone, etc.)
   - Photo upload
   - Dietary restrictions, allergies, medical accommodations
   - Emergency contact
   - Social media profiles

2. **Member Dashboard** (`[remember_dashboard]`)
   - Member's current status (pending vetting, vetted, etc.)
   - Vetting status and schedule
   - Approved roles
   - Event applications (pending, accepted, declined)
   - Payment status
   - Profile edit link

3. **Event Listings** (`[remember_events]`)
   - List of open/public events
   - Filter by date, location
   - Event details (dates, location, description)
   - Link to apply

4. **Event Application** (`[remember_event_application event_id="X"]`)
   - Apply to specific event
   - Select event role
   - Select merchandise (if available)
   - Review costs
   - Submit application

5. **Event Detail** (`[remember_event_detail event_id="X"]`)
   - Full event information
   - Available roles and costs
   - Merchandise catalog
   - Application button (if eligible)

6. **Payment Page** (`[remember_payment application_id="X"]`)
   - Payment details for accepted application
   - Role cost + merchandise total
   - Payment status
   - Record payment (if manual)
   - QuickBooks invoice link (if applicable)

7. **Vetting Status** (`[remember_vetting_status]`)
   - For applicants to see their vetting status
   - Scheduled vetting sessions
   - Vetting results

## Implementation Strategy

### Option 1: Page Configuration (Like WP Event Manager)
**Pros:**
- Admin controls which pages are used
- Can use existing pages
- Flexible page structure
- Easy to customize

**Cons:**
- Requires admin setup
- More configuration needed

**Implementation:**
- Settings page with page dropdowns for each shortcode
- On activation, suggest/auto-create pages if they don't exist
- Store page IDs in options
- Shortcodes can be placed anywhere, but we provide recommended pages

### Option 2: Auto-Create Pages on Activation
**Pros:**
- Zero configuration needed
- Works out of the box
- Consistent structure

**Cons:**
- Less flexible
- May create pages user doesn't want
- Harder to customize

### Option 3: Hybrid Approach (Recommended)
**Pros:**
- Best of both worlds
- Auto-create on activation with option to change
- Admin can reassign pages in settings
- Can use existing pages

**Implementation:**
1. On activation, check if pages exist
2. If not, offer to create them (with option to skip)
3. Store page IDs in settings
4. Settings page allows reassigning pages
5. Shortcodes work on any page, but we provide recommended pages

## Recommended Page Structure

```
Member Registration → /register/ or /member-registration/
Member Dashboard → /dashboard/ or /member-dashboard/
Event Listings → /events/ or /event-listings/
Event Detail → /events/[event-slug]/ (dynamic)
Event Application → /events/[event-slug]/apply/ (dynamic)
Payment → /payment/[application-id]/ (dynamic)
Vetting Status → /vetting-status/ or /my-vetting/
```

## Shortcode Features

Each shortcode should:
- Be theme-agnostic (works with classic and FSE)
- Handle user authentication (redirect if needed)
- Show appropriate messages
- Use WordPress styling hooks
- Be responsive
- Include proper nonces for forms
- Log actions for debugging

## Settings Integration

In Settings page:
- Page selection dropdowns for each shortcode type
- "Create Page" button if page doesn't exist
- Preview links to pages
- Option to reset to defaults

## Questions for Consideration

1. Should we auto-create pages on activation, or just suggest them?
2. Do we need separate pages for each function, or can some be combined?
3. Should event detail and application be on the same page?
4. Do we need a "My Applications" page separate from dashboard?
5. Should payment be integrated into dashboard or separate?
