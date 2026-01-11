# reMember Plugin - Testing Guide

## Activation Testing

### Step 1: Activate the Plugin

1. Log into WordPress admin panel
2. Navigate to **Plugins → Installed Plugins**
3. Find **reMember** plugin
4. Click **Activate**

### Step 2: Check for Errors

- Check for any PHP errors or warnings
- If errors occur, check error logs
- Common issues:
  - Missing database permissions
  - WordPress version too old (needs 5.0+)
  - PHP version incompatibility

### Step 3: Verify Database Tables Created

Run this SQL query in phpMyAdmin or your database tool:

```sql
SHOW TABLES LIKE 'wp_remember_%';
```

Expected: 26 tables should be created

**Table List**:
1. wp_remember_members
2. wp_remember_member_profiles
3. wp_remember_member_social_media
4. wp_remember_social_media_platforms
5. wp_remember_dietary_restrictions
6. wp_remember_member_dietary_restrictions
7. wp_remember_allergies
8. wp_remember_member_allergies
9. wp_remember_medical_accommodations
10. wp_remember_member_medical_accommodations
11. wp_remember_roles
12. wp_remember_member_roles
13. wp_remember_locations
14. wp_remember_events
15. wp_remember_event_roles
16. wp_remember_event_merchandise
17. wp_remember_event_applications
18. wp_remember_application_merchandise
19. wp_remember_products
20. wp_remember_payment_processors
21. wp_remember_payments
22. wp_remember_vetting
23. wp_remember_vetting_collaborators
24. wp_remember_vetting_notes
25. wp_remember_notification_settings
26. wp_remember_plugin_version

### Step 4: Verify Initial Data Seeded

Check these tables have default data:

```sql
-- Default location
SELECT * FROM wp_remember_locations;

-- Default roles (should have Administrator, Vetting, Admin)
SELECT * FROM wp_remember_roles;

-- Social media platforms (should have X (Twitter))
SELECT * FROM wp_remember_social_media_platforms;

-- Dietary restrictions (should have 10)
SELECT COUNT(*) FROM wp_remember_dietary_restrictions;

-- Allergies (should have 10)
SELECT COUNT(*) FROM wp_remember_allergies;

-- Medical accommodations (should have 6)
SELECT COUNT(*) FROM wp_remember_medical_accommodations;

-- Notification settings (should have 12)
SELECT COUNT(*) FROM wp_remember_notification_settings;

-- Payment processors (should have 2: Manual, QuickBooks)
SELECT * FROM wp_remember_payment_processors;
```

### Step 5: Verify Plugin Options

```sql
SELECT * FROM wp_options WHERE option_name = 'remember_version';
SELECT * FROM wp_options WHERE option_name = 'remember_options';
```

Expected:
- `remember_version` = '1.0.0'
- `remember_options` = serialized array with photo_max_size, photo_max_dimensions, qb_sync_interval

### Step 6: Check Admin Menu

1. Look for **reMember** menu item in WordPress admin sidebar
2. Should appear with groups icon
3. Click on it - should show admin page (currently placeholder)

### Step 7: Verify Capabilities

Check if current admin user has capabilities:

```sql
SELECT meta_value FROM wp_usermeta 
WHERE user_id = [YOUR_USER_ID] 
AND meta_key LIKE 'wp_capabilities';
```

Or check in WordPress admin: **Users → Your Profile** - capabilities should be listed

Expected capabilities:
- remember_manage_members
- remember_vet_applicants
- remember_view_vetting_notes
- remember_manage_events
- remember_view_events
- remember_manage_applications
- remember_manage_payments
- remember_manage_locations
- remember_manage_roles
- remember_manage_settings
- remember_view_reports

### Step 8: Test Deactivation

1. Deactivate the plugin
2. Verify data remains (tables should still exist)
3. Reactivate - should not create duplicate data (seeder checks for existing)

## Troubleshooting

### Plugin Won't Activate
- Check PHP error logs
- Verify WordPress version (5.0+)
- Check database permissions
- Ensure all files are present

### Database Errors
- Check database user permissions (CREATE TABLE, INSERT, etc.)
- Verify database charset (utf8mb4 recommended)
- Check for existing tables with same names

### Missing Data
- Check if seeder ran (should be in activation log)
- Manually check table row counts
- Verify no errors during seeding

## Next Steps After Successful Activation

Once activation is confirmed successful:
1. Review PROGRESS.md for next development steps
2. Continue building model classes
3. Build admin interface
4. Build public interface/shortcodes
5. Implement payment processors
6. Add QuickBooks integration
