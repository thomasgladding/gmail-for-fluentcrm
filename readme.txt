=== Gmail for FluentCRM ===
Contributors: gladdingdigital
Tags: fluentcrm, gmail, crm, contact history, email timeline
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display Gmail email history directly inside FluentCRM contact profiles.

== Description ==
Gmail for FluentCRM brings recent Gmail conversation history into each FluentCRM contact profile so your team can review context without leaving WordPress.

Features:
* Connect one or multiple Gmail accounts.
* Authorize each Gmail account independently using Google OAuth.
* Merge and deduplicate emails from all connected accounts by Gmail message ID.
* Control cache duration and number of emails shown per contact.
* Keep OAuth tokens encrypted at rest in WordPress options.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/gmail-for-fluentcrm` directory, or install through the WordPress plugins screen.
2. Activate the plugin in WordPress.
3. Go to `FluentCRM -> Gmail Integration`.
4. Add one or more account rows with label, Google OAuth Client ID, and Client Secret.
5. Save settings.
6. Authorize each account using the `Authorize` button.
7. Open a FluentCRM contact profile to view the Gmail timeline section.

== Frequently Asked Questions ==
= How do I get Google OAuth credentials? =
Create a project in Google Cloud Console, enable the Gmail API, then create OAuth 2.0 credentials. Use the callback URL shown on the plugin settings page.

= What permissions are needed? =
The plugin requests `https://www.googleapis.com/auth/gmail.readonly` to read message metadata/snippets for matching contact conversations.

= Is my data safe? =
OAuth token payloads are encrypted before storage using OpenSSL with a key derived from your WordPress salt. The plugin only reads Gmail data needed for timeline display.

== Screenshots ==
1. Settings screen with multiple Gmail accounts and OAuth controls.
2. FluentCRM contact profile showing merged Gmail timeline.
3. Display and cache options.

== Changelog ==
= 1.0.0 =
* Initial public release.
* Rebranded as Gmail for FluentCRM.
* Added support for multiple Gmail accounts.
* Added configurable cache duration and email display limits.
* Added uninstall cleanup, POT file, and WordPress.org readme.
