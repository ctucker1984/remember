# reMember Plugin Architecture

## Database Schema

### Core Tables

#### 1. wp_remember_members
- `member_id` BIGINT (FK to wp_users.ID, PRIMARY KEY)
- `status` ENUM('pending_vetting', 'in_vetting', 'vetted', 'rejected', 'inactive')
- `photo_url` VARCHAR(255) (path to uploaded photo)
- `created_at` DATETIME
- `updated_at` DATETIME

#### 2. wp_remember_member_profiles
- `profile_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `member_id` BIGINT (FK to wp_remember_members.member_id, UNIQUE)
- `legal_first_name` VARCHAR(100)
- `legal_last_name` VARCHAR(100)
- `address_street` VARCHAR(255)
- `address_city` VARCHAR(100)
- `address_state` VARCHAR(50)
- `address_postal` VARCHAR(20)
- `address_country` VARCHAR(100)
- `cell_phone` VARCHAR(50) (international format)
- `timezone` VARCHAR(50) (WordPress timezone format)
- `im_handle` VARCHAR(100)
- `im_type` VARCHAR(50) DEFAULT 'telegram'
- `interests` TEXT
- `emergency_contact_first` VARCHAR(100)
- `emergency_contact_last` VARCHAR(100)
- `emergency_contact_phone` VARCHAR(50)
- `emergency_contact_relationship` VARCHAR(50)
- `created_at` DATETIME
- `updated_at` DATETIME

#### 3. wp_remember_member_social_media
- `social_media_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `member_id` BIGINT (FK to wp_remember_members.member_id)
- `platform_id` BIGINT (FK to wp_remember_social_media_platforms.platform_id)
- `handle` VARCHAR(255)
- `created_at` DATETIME

#### 4. wp_remember_social_media_platforms (configurable lookup)
- `platform_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `platform_name` VARCHAR(100) UNIQUE
- `is_active` BOOLEAN DEFAULT 1
- `sort_order` INT DEFAULT 0
- `created_at` DATETIME

#### 5. wp_remember_dietary_restrictions (lookup)
- `restriction_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `restriction_name` VARCHAR(100) UNIQUE
- `is_active` BOOLEAN DEFAULT 1
- `sort_order` INT DEFAULT 0
- `created_at` DATETIME

#### 6. wp_remember_member_dietary_restrictions (junction)
- `member_id` BIGINT (FK to wp_remember_members.member_id)
- `restriction_id` BIGINT (FK to wp_remember_dietary_restrictions.restriction_id)
- PRIMARY KEY (member_id, restriction_id)

#### 7. wp_remember_allergies (lookup)
- `allergy_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `allergy_name` VARCHAR(100) UNIQUE
- `is_active` BOOLEAN DEFAULT 1
- `sort_order` INT DEFAULT 0
- `created_at` DATETIME

#### 8. wp_remember_member_allergies (junction)
- `member_id` BIGINT (FK to wp_remember_members.member_id)
- `allergy_id` BIGINT (FK to wp_remember_allergies.allergy_id)
- PRIMARY KEY (member_id, allergy_id)

#### 9. wp_remember_medical_accommodations (lookup)
- `accommodation_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `accommodation_name` VARCHAR(100) UNIQUE
- `description` TEXT
- `is_active` BOOLEAN DEFAULT 1
- `sort_order` INT DEFAULT 0
- `created_at` DATETIME

#### 10. wp_remember_member_medical_accommodations (junction)
- `member_id` BIGINT (FK to wp_remember_members.member_id)
- `accommodation_id` BIGINT (FK to wp_remember_medical_accommodations.accommodation_id)
- PRIMARY KEY (member_id, accommodation_id)

#### 11. wp_remember_roles
- `role_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `role_name` VARCHAR(100) UNIQUE
- `role_type` ENUM('event', 'system') DEFAULT 'event'
- `is_event_role` BOOLEAN DEFAULT 1
- `description` TEXT
- `created_at` DATETIME

#### 12. wp_remember_member_roles (junction - roles member is approved for)
- `member_role_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `member_id` BIGINT (FK to wp_remember_members.member_id)
- `role_id` BIGINT (FK to wp_remember_roles.role_id)
- `approved_at` DATETIME
- `approved_by` BIGINT (FK to wp_users.ID)
- `created_at` DATETIME
- UNIQUE KEY (member_id, role_id)

#### 13. wp_remember_locations
- `location_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `location_name` VARCHAR(255)
- `address_full` TEXT
- `details` TEXT
- `is_active` BOOLEAN DEFAULT 1
- `created_at` DATETIME
- `updated_at` DATETIME

#### 14. wp_remember_events
- `event_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `event_name` VARCHAR(255)
- `event_description` TEXT
- `location_id` BIGINT (FK to wp_remember_locations.location_id)
- `start_date` DATE
- `end_date` DATE
- `is_private` BOOLEAN DEFAULT 0
- `status` ENUM('draft', 'open', 'closed', 'completed', 'cancelled') DEFAULT 'draft'
- `created_by` BIGINT (FK to wp_users.ID)
- `created_at` DATETIME
- `updated_at` DATETIME

#### 15. wp_remember_event_roles (roles available for specific event)
- `event_role_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `event_id` BIGINT (FK to wp_remember_events.event_id)
- `role_id` BIGINT (FK to wp_remember_roles.role_id)
- `cost` DECIMAL(10,2) DEFAULT 0.00
- `max_participants` INT (NULL = unlimited)
- `current_count` INT DEFAULT 0 (denormalized for performance)
- `is_active` BOOLEAN DEFAULT 1
- `created_at` DATETIME

#### 16. wp_remember_event_merchandise
- `merchandise_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `event_id` BIGINT (FK to wp_remember_events.event_id)
- `merchandise_name` VARCHAR(255)
- `description` TEXT
- `cost` DECIMAL(10,2)
- `quickbooks_product_id` VARCHAR(100) (nullable, QB sync ID)
- `quickbooks_product_name` VARCHAR(255) (nullable, cached QB name)
- `max_quantity` INT (NULL = unlimited)
- `is_available` BOOLEAN DEFAULT 1
- `created_at` DATETIME
- `updated_at` DATETIME

#### 17. wp_remember_event_applications
- `application_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `event_id` BIGINT (FK to wp_remember_events.event_id)
- `member_id` BIGINT (FK to wp_remember_members.member_id)
- `event_role_id` BIGINT (FK to wp_remember_event_roles.event_role_id)
- `status` ENUM('pending', 'accepted', 'declined', 'cancelled', 'waitlisted')
- `applied_at` DATETIME
- `processed_at` DATETIME (when admin accepts/declines)
- `processed_by` BIGINT (FK to wp_users.ID, nullable)
- `notes` TEXT (admin notes)
- UNIQUE KEY (event_id, member_id, event_role_id) - member can only apply once per role per event

#### 18. wp_remember_application_merchandise (merchandise selections per application)
- `application_merchandise_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `event_application_id` BIGINT (FK to wp_remember_event_applications.application_id)
- `merchandise_id` BIGINT (FK to wp_remember_event_merchandise.merchandise_id)
- `quantity` INT DEFAULT 1
- `unit_cost` DECIMAL(10,2) (snapshot at time of application)
- `total_cost` DECIMAL(10,2)
- `created_at` DATETIME

#### 19. wp_remember_payments
- `payment_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `event_application_id` BIGINT (FK to wp_remember_event_applications.application_id, UNIQUE)
- `member_id` BIGINT (FK to wp_remember_members.member_id)
- `processor_id` BIGINT (FK to wp_remember_payment_processors.processor_id)
- `role_cost` DECIMAL(10,2)
- `merchandise_cost` DECIMAL(10,2)
- `total_amount` DECIMAL(10,2)
- `payment_status` ENUM('pending', 'partial', 'paid', 'refunded', 'cancelled') DEFAULT 'pending'
- `payment_date` DATETIME (nullable)
- `payment_method` VARCHAR(50) (manual, quickbooks, etc.)
- `transaction_id` VARCHAR(255) (nullable, QB invoice ID, etc.)
- `quickbooks_invoice_id` VARCHAR(100) (nullable, for QB sync)
- `notes` TEXT
- `created_at` DATETIME
- `updated_at` DATETIME

#### 20. wp_remember_payment_processors
- `processor_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `processor_type` ENUM('manual', 'quickbooks') UNIQUE
- `processor_name` VARCHAR(100)
- `is_active` BOOLEAN DEFAULT 0
- `settings` TEXT (JSON - stores QB OAuth tokens, client IDs, etc.)
- `last_sync_at` DATETIME (nullable)
- `created_at` DATETIME
- `updated_at` DATETIME

#### 21. wp_remember_vetting
- `vetting_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `member_id` BIGINT (FK to wp_remember_members.member_id, UNIQUE)
- `primary_vetter_id` BIGINT (FK to wp_users.ID)
- `status` ENUM('pending', 'scheduled', 'in_progress', 'completed') DEFAULT 'pending'
- `scheduled_at` DATETIME (nullable)
- `completed_at` DATETIME (nullable)
- `decision` ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending'
- `decision_date` DATETIME (nullable)
- `created_at` DATETIME
- `updated_at` DATETIME

#### 22. wp_remember_vetting_collaborators (invited vetting team members)
- `collaborator_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `vetting_id` BIGINT (FK to wp_remember_vetting.vetting_id)
- `member_id` BIGINT (FK to wp_users.ID - the collaborator)
- `invited_by` BIGINT (FK to wp_users.ID)
- `invited_at` DATETIME
- `status` ENUM('pending', 'accepted', 'declined') DEFAULT 'pending'
- UNIQUE KEY (vetting_id, member_id)

#### 23. wp_remember_vetting_notes
- `note_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `vetting_id` BIGINT (FK to wp_remember_vetting.vetting_id)
- `member_id` BIGINT (FK to wp_users.ID - who created the note)
- `note_content` TEXT
- `is_admin_only` BOOLEAN DEFAULT 0
- `created_at` DATETIME

#### 24. wp_remember_products (QuickBooks product mapping)
- `product_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `product_name` VARCHAR(255) UNIQUE
- `description` TEXT
- `quickbooks_product_id` VARCHAR(100) UNIQUE (QB Item ID)
- `quickbooks_product_name` VARCHAR(255) (cached QB name)
- `product_type` VARCHAR(50) (Service, Inventory, etc.)
- `is_active` BOOLEAN DEFAULT 1
- `last_sync_at` DATETIME (nullable)
- `created_at` DATETIME
- `updated_at` DATETIME

#### 25. wp_remember_notification_settings
- `setting_id` BIGINT PRIMARY KEY AUTO_INCREMENT
- `notification_type` VARCHAR(100) UNIQUE (e.g., 'application_received', 'vetting_completed', etc.)
- `is_enabled` BOOLEAN DEFAULT 1
- `subject_template` VARCHAR(255)
- `body_template` TEXT
- `created_at` DATETIME
- `updated_at` DATETIME

#### 26. wp_remember_plugin_version (for migrations)
- `version` VARCHAR(20) PRIMARY KEY
- `applied_at` DATETIME

## Key Relationships

- Member → Member Profile (1:1)
- Member → Member Roles (1:many)
- Member → Event Applications (1:many)
- Member → Vetting (1:1)
- Event → Event Roles (1:many)
- Event → Event Merchandise (1:many)
- Event Application → Payment (1:1)
- Event Application → Application Merchandise (1:many)
- Vetting → Vetting Notes (1:many)
- Vetting → Vetting Collaborators (1:many)

## Plugin Structure

```
remember/
├── remember.php (main plugin file)
├── includes/
│   ├── class-remember.php (main plugin class)
│   ├── class-remember-activator.php
│   ├── class-remember-deactivator.php
│   ├── class-remember-loader.php (hooks/actions)
│   │
│   ├── database/
│   │   ├── class-remember-database.php (schema creation)
│   │   ├── class-remember-migrator.php (version migrations)
│   │   ├── class-remember-seeder.php (initial data)
│   │   └── migrations/ (versioned SQL/PHP migration files)
│   │       ├── 1.0.0-initial-schema.php
│   │       └── ...
│   │
│   ├── models/
│   │   ├── class-member.php
│   │   ├── class-event.php
│   │   ├── class-application.php
│   │   ├── class-payment.php
│   │   ├── class-vetting.php
│   │   ├── class-location.php
│   │   ├── class-role.php
│   │   └── class-merchandise.php
│   │
│   ├── admin/
│   │   ├── class-remember-admin.php
│   │   ├── class-remember-settings.php
│   │   ├── class-remember-members-admin.php
│   │   ├── class-remember-events-admin.php
│   │   ├── class-remember-vetting-admin.php
│   │   └── views/
│   │
│   ├── public/
│   │   ├── class-remember-public.php
│   │   ├── class-remember-shortcodes.php
│   │   └── views/
│   │
│   ├── api/
│   │   ├── class-remember-rest-api.php (WP REST API endpoints)
│   │   └── endpoints/
│   │
│   ├── payments/
│   │   ├── interface-payment-processor.php
│   │   ├── class-manual-processor.php
│   │   └── class-quickbooks-processor.php
│   │
│   ├── integrations/
│   │   └── class-remember-quickbooks.php (QB API wrapper)
│   │
│   ├── notifications/
│   │   ├── class-remember-notifications.php
│   │   ├── class-remember-email-templates.php
│   │   └── templates/
│   │
│   ├── gdpr/
│   │   └── class-remember-gdpr-export.php
│   │
│   ├── uploads/
│   │   └── class-remember-file-handler.php (photo uploads)
│   │
│   └── utilities/
│       ├── class-remember-capabilities.php
│       ├── class-remember-validators.php
│       ├── class-remember-helpers.php
│       └── class-remember-permissions.php
│
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── public.css
│   ├── js/
│   │   ├── admin.js
│   │   └── public.js
│   └── images/
│
├── languages/ (i18n .pot/.po files)
│
└── templates/ (override-able templates)
    ├── admin/
    └── public/
```

## QuickBooks Integration Details

### OAuth 2.0 Flow
- Store client ID, client secret, redirect URI
- Token refresh mechanism
- Store tokens encrypted in payment processor settings

### Product Mapping
- Admin maps WP products to QB products
- Two-way sync: WP → QB (create invoices), QB → WP (check payments)
- Store QB product IDs for invoice creation

### Customer Sync
- Sync minimal member data to QB as customers
- Fields: Name (legal first + last), Email, Phone, Address
- Store QB customer ID in member meta

### Invoice Creation
- When event application accepted → create QB invoice
- Invoice line items: Role cost + merchandise
- Link invoice ID to payment record
- Check QB payment status periodically via cron

## Notification Types

1. `application_received` - New member application
2. `vetting_assigned` - Vetter assigned to applicant
3. `vetting_scheduled` - Vetting session scheduled
4. `vetting_completed` - Vetting decision made
5. `member_vetted` - Member notified of vetting result
6. `event_application_submitted` - Member applied to event
7. `event_application_accepted` - Application accepted
8. `event_application_declined` - Application declined
9. `event_application_waitlisted` - Application waitlisted
10. `payment_recorded` - Payment recorded (manual or QB)
11. `payment_due_reminder` - Payment due reminder
12. `vetting_collaborator_invited` - Collaborator invited to vetting

## WordPress Capabilities

- `remember_manage_members` - Full member management
- `remember_vet_applicants` - Assign and perform vetting
- `remember_view_vetting_notes` - View vetting notes
- `remember_manage_events` - Create and manage events
- `remember_view_events` - View events
- `remember_manage_applications` - Accept/decline event applications
- `remember_manage_payments` - Record and manage payments
- `remember_manage_locations` - Manage locations
- `remember_manage_roles` - Manage event/system roles
- `remember_manage_settings` - Plugin settings
- `remember_view_reports` - View reports/analytics

## Migration Strategy

- Store current version in wp_remember_plugin_version
- Migration files in includes/database/migrations/
- Each migration checks if already applied
- Migrator runs on plugin activation/update
- Version numbers follow semantic versioning

## GDPR Export

- Export all member data including:
  - Profile information
  - Event applications
  - Payment history
  - Vetting records and notes
  - All associated data
- Generate JSON/CSV export file
- Allow admin to download for member

## Activation Sequence

1. Check WordPress version compatibility
2. Create all database tables
3. Run migrations (if updating)
4. Seed initial data:
   - Default location
   - Default roles (Administrator event role, Vetting, Admin system roles)
   - Default social media platforms (X/Twitter)
   - Default dietary restrictions, allergies, medical accommodations
5. Create default notification settings
6. Set up capabilities
7. Assign current user as first admin
8. Store plugin version
9. Set default plugin options

## Implementation Details

### Photo Uploads
- **Format**: Square profile photos (1:1 aspect ratio)
- **Max dimensions**: 800x800px (cropped/resized as needed)
- **File formats**: JPG, PNG
- **Max file size**: 2MB (WordPress default, configurable)
- **Storage**: WordPress media library (wp-content/uploads/remember/)
- **Processing**: Auto-resize/crop to square on upload

### QuickBooks Credentials Storage
- **Method**: Encrypted storage in database (wp_remember_payment_processors.settings)
- **Encryption**: WordPress `wp_salt()` and encryption functions
- **Fields**: OAuth tokens (access_token, refresh_token), client_id, client_secret, company_id
- **Token refresh**: Automatic refresh before expiration

### Application Cancellation
- Members can cancel their own applications
- Cancellation sets status to 'cancelled'
- Cancelled applications free up spots (decrement current_count)
- Payment records remain for audit trail (marked as cancelled)

### Vetting Rejection & Reapplication
- Rejected members can reapply (new vetting record)
- Duplicate handling: Check for existing member by email/phone before creating new record
- Merge functionality: Future enhancement for duplicate merging
- Status workflow: rejected → (can reapply) → pending_vetting

### Payment Tracking
- **Partial payments**: Payment status tracks 'partial', 'paid', 'pending'
- **Deposit support**: Track deposit amount vs. total amount due
- **Payment records**: Store amount_paid, amount_due, total_amount
- **Multiple payments**: Allow recording multiple payment transactions per application

### Event Date Handling
- **Single day events**: start_date = end_date
- **Multi-day events**: start_date < end_date
- **Date validation**: Ensure end_date >= start_date
- **Date storage**: DATE fields (no time component)
- **Time zones**: Use member's timezone for display, store in UTC

### QuickBooks Sync
- **Cron interval**: Once per hour (wp_schedule_event)
- **Admin trigger**: Sync on admin backend access (if > 5 minutes since last sync)
- **Sync display**: Show last_sync_at timestamp in admin UI (payment settings page)
- **Sync process**: Check payment status for all pending/partial payments with QB invoices
- **Background processing**: Use WP Cron or action scheduler for large syncs

## Key Technical Decisions

1. **WordPress Native**: No external frameworks (CodeIgniter, etc.)
2. **Custom Tables**: For complex relational data
3. **OOP Architecture**: Namespaced classes, PSR-4 style
4. **WP REST API**: For AJAX calls from frontend
5. **Shortcodes**: Primary frontend interface
6. **Capabilities**: WordPress native permission system
7. **Photo Uploads**: WordPress media library integration (800x800px square)
8. **Email**: wp_mail() with configurable templates
9. **Cron**: For QB payment sync (hourly) + admin-triggered sync
10. **Encryption**: WordPress encryption for QB credentials (database storage)
11. **Payment tracking**: Support partial payments and deposits
12. **Events**: Support both single-day and multi-day events
