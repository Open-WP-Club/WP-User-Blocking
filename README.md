# WP User Blocking

## Description

WP User Blocking is a WordPress plugin that allows administrators to restrict access to their website for specific users based on email addresses or IP addresses. It provides a customizable block message and offers features such as email export/import functionality and debug mode.

## Features

- Block users by email address
- Block users by IP address
- Customizable block message
- Option to block admin users
- Export and import blocked email addresses
- Debug mode for testing
- wp-config.php debug mode override
- Bulk actions to block/unblock users
- User-friendly settings page in WordPress admin

## Installation

1. Download the plugin zip file.
2. Log in to your WordPress admin panel.
3. Go to Plugins > Add New.
4. Click on the "Upload Plugin" button.
5. Choose the downloaded zip file and click "Install Now".
6. After installation, click "Activate Plugin".

## Usage

### Accessing the Settings

1. Log in to your WordPress admin panel.
2. Go to Users > User Blocking.

### Configuring the Plugin

1. **Block Message**: Customize the message displayed to blocked users.
2. **Blocked Emails**: Enter email addresses to block, one per line.
3. **Blocked IPs**: Enter IP addresses to block, one per line.
4. **Button Email**: Set an email address for the "Contact Us" button on the block page.
5. **Debug Mode**: Enable to allow access to wp-admin for testing.
6. **Block Admins**: Enable to block users with administrator roles.

### Blocking/Unblocking Individual Users

1. Go to the Users page in the WordPress admin.
2. Hover over a user and click "Block User" or "Unblock User".

### Bulk Blocking/Unblocking Users

1. Go to the Users page in the WordPress admin.
2. Select multiple users using the checkboxes.
3. Choose "Block Users" or "Unblock Users" from the Bulk Actions dropdown.
4. Click "Apply".

### Exporting Blocked Emails

1. Go to Users > User Blocking.
2. Click the "Export Blocked Emails" button.
3. A CSV file will be downloaded with all blocked email addresses.

### Importing Blocked Emails

1. Go to Users > User Blocking.
2. Click "Choose File" under the Import Blocked Emails section.
3. Select a CSV file containing email addresses to block.
4. Click the "Import Blocked Emails" button.

Note: The CSV file should have a header row with "Blocked Emails" as the column name. The import function will skip this header row.

### Debug Mode

You can enable debug mode in two ways:

1. Check the "Debug Mode" box in the plugin settings.
2. Add the following line to your wp-config.php file:

   ```php
   define('WP_USER_BLOCKING_DEBUG', true);
   ```

   This will override the setting in the admin panel.

## Frequently Asked Questions

**Q: Will this plugin block existing logged-in users?**
A: Yes, the plugin checks user access on each page load, so it will block existing sessions for newly blocked users.

**Q: Can I block entire IP ranges?**
A: Currently, the plugin only supports blocking individual IP addresses. IP range blocking may be added in future updates.

**Q: What happens if I block my own email or IP?**
A: If you block your own email or IP, you may lose access to your site. Always keep debug mode on or have an alternate admin account when testing.

**Q: How do I unblock a user?**
A: You can unblock a user by removing their email from the Blocked Emails list in the plugin settings, or by using the "Unblock User" action on the Users page.

## Contributing

We welcome contributions to the WP User Blocking plugin. Please fork the repository and submit a pull request with your changes.

## License

WP User Blocking is licensed under the GPL v2 or later.

---

© 2024 OpenWPclub.com. All rights reserved.
