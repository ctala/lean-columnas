=== Lean Columnas ===
Contributors: ctala
Tags: opinion columns, columnists, editorial workflow, agency management, opinion articles
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Opinion columns management with Columnist and Agency roles, editorial workflow, quality gates, and OpinionNewsArticle schema markup.

== Description ==

**Lean Columnas** is a WordPress plugin for managing opinion columns at scale. It provides a complete editorial workflow for columnists and agencies, with built-in quality gates and SEO-optimized structured data.

= Key Features =

* **Custom Roles**: Columnista, Agencia, and Column Editor roles with granular capabilities
* **Editorial Workflow**: Draft, Submitted, In Review, Returned, Approved, Rejected, Published statuses
* **Quality Gates**: Automatic validation of word count (600-3000), title length, excerpt, featured image, and subheadings before submission
* **Agency Management**: Agencies can be assigned columnists and manage their roster
* **SEO Schema Markup**: Automatic OpinionNewsArticle JSON-LD on column pages for E-E-A-T
* **Theme-Independent Templates**: Plugin templates that can be overridden from your theme
* **Performance First**: Uses WordPress user meta (no custom tables), loads assets only on relevant pages
* **GeneratePress Compatible**: Structural CSS only, inherits theme typography and colors

= For Columnists =

Columnists can log in to WordPress, create columns, and submit them for editorial review. They can track the status of their submissions and respond to editorial feedback.

= For Agencies =

Agencies can be assigned columnists and track all columns from their roster. Agency-columnist relationship is managed via WordPress user meta.

= For Editors =

Column Editors have a dedicated editorial queue in wp-admin to review, approve, return with notes, or reject submitted columns. Quality gates ensure consistent content standards.

= Template Override =

Override plugin templates by copying them to your theme:

* `your-theme/lean-columnas/single-columna-opinion.php`
* `your-theme/lean-columnas/archive-columna-opinion.php`
* `your-theme/lean-columnas/parts/column-card.php`

== Installation ==

1. Upload the `lean-columnas` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create custom roles and register post statuses
4. Go to Columnas in the admin menu to manage the editorial queue

== Frequently Asked Questions ==

= Can columnists create their own WordPress accounts? =

No, user accounts must be created by a WordPress administrator. Once the account exists and has the Columnista role, the columnist can log in and manage their columns.

= Can agencies create new columnist accounts? =

No. Agencies can only be assigned existing columnists. User account creation is handled by site administrators.

= What happens when I deactivate the plugin? =

Deactivation only flushes rewrite rules. All data (roles, content, user meta) is preserved. Data is only removed when you delete the plugin through the WordPress admin.

= Does the plugin affect site performance? =

No. The plugin uses native WordPress user meta (no custom tables), only loads CSS on relevant pages, and adds zero database queries to frontend page loads.

== Changelog ==

= 0.1.0 =
* Initial release
* Custom post type: columna-opinion
* Custom taxonomy: columna-categoria
* Custom roles: Columnista, Agencia, Column Editor capabilities
* Editorial workflow with quality gates
* OpinionNewsArticle schema markup
* Agency-columnist relationship via user meta
* Theme-overridable templates
* Admin editorial queue with status tabs
