=== Ticket Status Sync for FluentSupport to MainWP ===
Plugin Name: Ticket Status Sync for FluentSupport to MainWP
Plugin URI: https://github.com/sflwa/fs-mainwp
Description: Integrates FluentSupport ticket data from a single "Support Site" into the MainWP Dashboard.
Version: 1.2.2
Author: South Florida Web Advisors
Author URI: https://sflwa.net
Requires at least: 6.7
Requires PHP: 7.4
Tested up to: 6.9
Stable tag: 1.2.2
License: GPLv2 or later

Integrates FluentSupport ticket data from a single "Support Site" into the MainWP Dashboard.

== Description ==

This MainWP Extension integrates ticket data from a FluentSupport installation on a dedicated "Support Site" directly into your MainWP Dashboard.

It provides centralized visibility over your support workflow by displaying active tickets in a dashboard widget and on a dedicated extension page. It uses the FluentSupport REST API and Application Passwords for secure, direct synchronization on a 5-minute background schedule, preventing site synchronization slowdowns.

**Features include:**
* FluentSupport Tickets Dashboard Widget.
* Dedicated Tickets Overview page.
* Background synchronization every 5 minutes (via WP-Cron).
* API connection test and credential management.

== Pre-Configuration: FluentSupport Custom Field ==

For the extension to correctly map support tickets to your individual MainWP client sites, you must create a custom text field in FluentSupport on your Support Site with a specific key. This field should store the client's website URL when a ticket is created.

1.  On your FluentSupport site, go to **Global Settings > Custom Fields**.
2.  Click **Add New Field**.
3.  Set the Field Label (e.g., "Client Website URL").
4.  Crucially, ensure the **Field Key** is set to **`cf_website_url`**.
5.  Set the Field Type to **Text Input** or **URL**.
6.  Save the Custom Field and ensure it is included in your active support forms.

This exact key (`cf_website_url`) is required by the extension to match ticket data to your managed sites.

== Installation ==

1. Please install the **MainWP Dashboard** plugin and activate it before installing this extension (get the MainWP Dashboard plugin from url: https://mainwp.com/).
2. Upload the `fs-mainwp` folder to the `/wp-content/plugins/` directory.
3. Activate the FluentSupport Integration for MainWP plugin through the 'Plugins' menu in WordPress.
4. Navigate to the extension's **Settings** tab on your MainWP dashboard and enter the URL and REST API Application Credentials for your Support Site.

== Changelog ==

= 1.2.2 =
* Version Bump for WordPress 6.9
* ADD: Dismisable Admin Alert to let Dev know you are using the plugin

= 1.2.1 =
* FIX: Improved coding standards compliance across widget and overview classes.
* FIX: Corrected internationalization and output escaping issues.
* DEV: Refactored sync to a 5-minute background WP-Cron job to prevent site synchronization slowdowns.
* DEV: Added Last Synced time display to the Dashboard widget.

= 1.2.0 =
* Initial development release.