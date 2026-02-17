# Gmail for FluentCRM - Setup Guide

Welcome! This guide will walk you through setting up the **Gmail for FluentCRM** plugin by Gladding Digital. This plugin adds a custom tab to your FluentCRM contact profiles that displays Gmail email correspondence with each contact — making it easy to see your email history without leaving FluentCRM.

---

## 1. Google Cloud Console Setup

Before you can use the plugin, you need to create a Google Cloud project and enable the Gmail API. Don't worry — it's free for normal use and we'll walk through it step by step.

### Step 1: Create a Google Cloud Project

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Sign in with your Google account (use the Gmail account you want to connect)
3. Click **Select a project** (top left, next to "Google Cloud")
4. Click **New Project**
5. Give your project a name (e.g., "FluentCRM Gmail Integration")
6. Click **Create**
7. Wait a few seconds for the project to be created, then select it from the dropdown

### Step 2: Enable the Gmail API

1. With your new project selected, go to the [Gmail API page](https://console.cloud.google.com/apis/library/gmail.googleapis.com)
2. Click the blue **Enable** button
3. Wait a moment for the API to be enabled

### Step 3: Configure the OAuth Consent Screen

Google needs to know what permissions your app will request. Here's how to set that up:

1. Go to [OAuth consent screen](https://console.cloud.google.com/apis/credentials/consent)
2. Select **External** as the User Type (unless you have a Google Workspace account and want to restrict this to your organization)
3. Click **Create**
4. Fill in the required fields:
   - **App name:** `FluentCRM Gmail Integration` (or your site name)
   - **User support email:** Your email address
   - **Developer contact email:** Your email address
5. Click **Save and Continue**
6. On the **Scopes** screen, click **Add or Remove Scopes**
7. Filter or search for `gmail.readonly`
8. Check the box next to `.../auth/gmail.readonly` — this allows read-only access to Gmail
9. Click **Update** at the bottom
10. Click **Save and Continue**
11. On the **Test users** screen, click **Save and Continue** (you can skip adding test users if you published the app)
12. Review the summary and click **Back to Dashboard**

### Step 4: Create OAuth 2.0 Credentials

Now we'll create the credentials you'll enter into the WordPress plugin:

1. Go to [Credentials](https://console.cloud.google.com/apis/credentials)
2. Click **+ Create Credentials** (top of page)
3. Select **OAuth client ID**
4. For **Application type**, select **Web application**
5. Give it a name (e.g., "FluentCRM Plugin")
6. Under **Authorized redirect URIs**, click **+ Add URI**
7. Enter your redirect URI in this exact format:
   ```
   https://yourdomain.com/wp-admin/admin-ajax.php?action=gfcrm_oauth_callback
   ```
   Replace `yourdomain.com` with your actual WordPress site domain. **The URI must match exactly or authentication will fail.**
8. Click **Create**
9. A popup will appear with your **Client ID** and **Client Secret** — copy both and save them somewhere safe (you'll need them in the next step)
10. Click **OK**

✅ **You're done with Google Cloud Console!** Now let's install and configure the plugin.

---

## 2. Plugin Installation

### Requirements

- WordPress 5.0 or higher
- **FluentCRM** (free or Pro) must be installed and active
- PHP 7.4 or higher

### Installation Steps

1. Download the plugin ZIP file (or clone the repository)
2. Upload the plugin folder to `wp-content/plugins/` on your WordPress site
3. The folder should be named `gd-fluentcrm-gmail` (or similar)
4. Go to **Plugins** in your WordPress admin
5. Find **Gmail for FluentCRM** in the list
6. Click **Activate**

✅ **Plugin installed!** Let's connect it to Google.

---

## 3. Plugin Configuration

### Connect Your Gmail Account

1. In your WordPress admin, go to **FluentCRM → Gmail Integration** (or **FluentCRM → Settings → Gmail Integration** depending on your version)
2. You'll see a form to add a Gmail account
3. Enter the **Client ID** you copied from Google Cloud Console
4. Enter the **Client Secret** you copied from Google Cloud Console
5. Click **Save Settings**
6. A new button will appear: **Authorize with Google**
7. Click it — you'll be redirected to Google
8. Sign in with your Gmail account (if not already signed in)
9. Review the permissions (the plugin only requests `gmail.readonly` — read-only access)
10. Click **Allow** to grant access
11. You'll be redirected back to WordPress
12. The status should now show **Connected** with your email address

🎉 **You're connected!** Now let's see it in action.

---

## 4. Usage

### Viewing Gmail Emails in Contact Profiles

1. Go to **FluentCRM → Contacts**
2. Click on any contact to view their profile
3. You'll see a new tab called **Gmail Emails**
4. Click the tab to see email correspondence between your Gmail account and this contact
5. By default, the plugin shows the **last 10 emails** (this is configurable in settings)
6. Click any email subject to open the full thread in Gmail in a new tab

### How It Works

- The plugin searches your Gmail for emails to/from the contact's email address
- Emails are displayed in reverse chronological order (newest first)
- Each email shows:
  - Subject line
  - Date/time
  - Snippet (preview of the email body)
  - Link to open in Gmail
- Results are **cached for 15 minutes** (configurable) to avoid hitting Google API rate limits

### Configuration Options

You can adjust these settings in **FluentCRM → Gmail Integration**:

- **Number of emails to display:** Change from the default 10 to any number you like
- **Cache duration:** How long to store results before fetching fresh data from Gmail
- **Date range filter:** Optionally limit results to the last 30/60/90 days

---

## 5. Multiple Gmail Accounts

Need to search across multiple Gmail accounts? No problem!

### Adding Additional Accounts

1. Go to **FluentCRM → Gmail Integration**
2. Click **Add Another Account**
3. Enter the Client ID and Client Secret for the new account (you can use the same Google Cloud project or create a separate one)
4. Click **Save** and then **Authorize with Google**
5. Repeat for as many accounts as you need

### How Multiple Accounts Work

- When viewing a contact's Gmail tab, the plugin searches **all connected accounts**
- Results are merged into a **single timeline** sorted by date
- Each email shows which account it came from
- This is useful if you have separate addresses like:
  - `sales@yourcompany.com`
  - `support@yourcompany.com`
  - `info@yourcompany.com`

### Managing Accounts

- To disconnect an account, click **Disconnect** next to it in the settings
- To re-authorize an account, click **Reconnect**
- You can temporarily disable an account without removing it

---

## 6. Troubleshooting

### "Not authorized" or "Authentication expired" message

**Solution:** Your OAuth token may have expired or been revoked. Go to **FluentCRM → Gmail Integration** and click **Reconnect** next to the account.

---

### No emails showing for a contact

**Possible causes:**

1. **Contact has no email address** — Check the contact's profile has a valid email in the "Email" field
2. **No correspondence exists** — The plugin can only show emails that exist in your Gmail. Check your Gmail directly to confirm you've exchanged emails with this address
3. **Emails are outside the date range** — If you've set a date filter (e.g., "last 30 days"), older emails won't appear
4. **Wrong email address** — Make sure the contact's email in FluentCRM matches exactly what's in your Gmail

---

### OAuth error during authorization

**Common issues:**

- **Redirect URI mismatch:** Go back to Google Cloud Console and verify the redirect URI matches exactly:
  ```
  https://yourdomain.com/wp-admin/admin-ajax.php?action=gfcrm_oauth_callback
  ```
  Check for `http://` vs `https://`, `www.` vs no `www.`, trailing slashes, etc.
  
- **Wrong Client ID or Secret:** Double-check you copied them correctly from Google Cloud Console

- **App not published:** If you see a warning about the app being unverified, click **Advanced** → **Go to [App Name] (unsafe)** — this is normal for private integrations

---

### Token refresh issues

If the plugin keeps asking you to re-authorize:

1. Go to **FluentCRM → Gmail Integration**
2. Click **Disconnect** for the problematic account
3. Wait 10 seconds
4. Click **Add Account** and go through the authorization flow again
5. If the problem persists, try creating a new OAuth client ID in Google Cloud Console

---

### Rate limit errors

Google's Gmail API has generous limits, but if you're checking many contacts frequently you might hit them.

**Solutions:**
- Increase the cache duration (e.g., from 15 minutes to 1 hour)
- Reduce the number of emails fetched per contact
- Wait a few minutes and try again

---

## 7. Privacy & Security

We take your data seriously. Here's what you need to know:

### Read-Only Access

- The plugin uses the `gmail.readonly` OAuth scope
- This means it can **only read** your emails
- It **cannot** send, delete, modify, or access any other Google services
- Even if the plugin were compromised, the worst it could do is read email — it cannot take any destructive action

### Token Encryption

- OAuth access tokens are stored in your WordPress database
- They are encrypted using **AES-256-CBC** encryption with your WordPress `AUTH_SALT` as the key
- This means tokens are unreadable without access to your `wp-config.php` file

### Data Storage

- Email data is cached temporarily using **WordPress transients** (typically stored for 15 minutes)
- No email content is stored permanently in the database
- When the cache expires, the data is automatically deleted
- Disconnecting an account removes all associated tokens and cached data

### Third-Party Access

- **No data is sent to any third party** (not even Gladding Digital!)
- All API calls go directly from your WordPress server to Google's servers
- The plugin does not "phone home" or transmit any information outside of Google's official API

### Recommendations

- Use HTTPS on your WordPress site (required for secure OAuth)
- Keep the plugin up to date
- Only authorize accounts you trust
- If you deactivate the plugin, tokens remain encrypted in the database — delete the plugin to remove them completely

---

## Need Help?

- **Documentation:** You're reading it! 📖
- **Support:** Contact Gladding Digital for assistance
- **Bug reports:** Please report any issues you encounter

---

**Enjoy seamless email integration with FluentCRM!** 🎉
