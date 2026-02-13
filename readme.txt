=== Rext AI Publisher ===
Contributors: rextai
Tags: ai, content, publishing, automation, seo
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Seamlessly publish AI-generated content from Rext AI directly to your WordPress site with full SEO support.

== Description ==

**Rext AI Publisher** connects your WordPress site to [Rext AI](https://rext.ai), enabling seamless content publishing from the Rext AI platform. Create, schedule, and manage AI-generated content directly from Rext AI with full support for categories, tags, featured images, and SEO metadata.

= Key Features =

* **Secure API Integration** - Industry-standard authentication with auto-generated API keys
* **Full Post Management** - Create, edit, and delete posts remotely
* **Media Support** - Upload images directly or import from URLs
* **SEO Integration** - Works with Yoast SEO, Rank Math, All in One SEO, and SEOPress
* **Activity Logging** - Track all API activity with detailed logs
* **Granular Permissions** - Control exactly what actions Rext AI can perform

= Supported SEO Plugins =

Rext AI Publisher automatically detects and integrates with these popular SEO plugins:

* Yoast SEO
* Rank Math
* All in One SEO
* SEOPress

When publishing content, SEO metadata (focus keyword, meta title, meta description, canonical URL) is automatically saved to your active SEO plugin.

= REST API Endpoints =

The plugin provides a comprehensive REST API for content management:

* **Connection** - Verify and manage the integration
* **Posts** - Create, read, update, and delete posts
* **Media** - Upload files and sideload from URLs
* **Taxonomies** - Manage categories, tags, and authors
* **SEO** - Get and update SEO metadata

= Requirements =

* WordPress 5.6 or higher
* PHP 7.4 or higher
* A Rext AI account ([sign up here](https://rext.ai))

== Installation ==

1. Upload the `rext-ai-publisher` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Rext AI** in the admin menu
4. Copy your Site URL and API Key
5. In Rext AI, add a new WordPress connection and paste your credentials
6. Start publishing content!

= Manual Installation =

1. Download the plugin zip file
2. Go to **Plugins > Add New** in WordPress
3. Click **Upload Plugin** and select the zip file
4. Click **Install Now** and then **Activate**

== Frequently Asked Questions ==

= How do I get an API key? =

An API key is automatically generated when you activate the plugin. You can find it in **Rext AI > Settings** in your WordPress admin.

= Can I regenerate my API key? =

Yes! Go to **Rext AI > Settings** and click the "Regenerate" button. Note that this will disconnect any active integrations until you update the key in Rext AI.

= Which SEO plugins are supported? =

We support Yoast SEO, Rank Math, All in One SEO, and SEOPress. The plugin automatically detects which one is active on your site.

= Is HTTPS required? =

While not strictly required, we strongly recommend using HTTPS for secure API communication. The plugin will show a warning if you're not using HTTPS.

= Can I control what Rext AI can do? =

Yes! The Permissions section in settings lets you enable or disable specific actions like creating posts, editing posts, uploading media, and managing taxonomies.

= Is my data secure? =

Yes. The plugin uses:

* Secure API key authentication
* Rate limiting to prevent abuse
* Optional request signatures for additional security
* HTTPS recommended for all communications

= How do I view activity logs? =

Go to **Rext AI > Activity Log** to see all API activity. You can filter by level (info, warning, error) and export logs to CSV.

== Screenshots ==

1. Settings page with API configuration and permissions
2. Activity log showing API requests
3. Published content management page
4. SEO plugin detection

== Changelog ==

= 1.0.0 =
* Initial release
* Full REST API for content management
* Support for Yoast SEO, Rank Math, All in One SEO, and SEOPress
* Media upload and sideload functionality
* Activity logging with export
* Granular permission controls

== Upgrade Notice ==

= 1.0.0 =
Initial release of Rext AI Publisher.

== Privacy Policy ==

Rext AI Publisher connects your WordPress site to the Rext AI platform. When using this plugin:

* Your site URL and connection status are shared with Rext AI
* Content published through the API is stored on your WordPress site
* Activity logs are stored locally and can be exported or deleted

For more information, please review the [Rext AI Privacy Policy](https://rext.ai/privacy).

== Support ==

For support, please visit:

* [Documentation](https://docs.rext.ai/wordpress)
* [Support Forum](https://wordpress.org/support/plugin/rext-ai-publisher)
* [Contact Us](https://rext.ai/contact)
