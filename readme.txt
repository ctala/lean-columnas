=== Lean Columnas ===
Contributors: ctala
Tags: opinion columns, columnists, editorial workflow, agency management, opinion articles
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Opinion columns management with Columnist and Agency roles, editorial workflow, quality gates, and OpinionNewsArticle schema markup.

== Description ==

**Lean Columnas** is a WordPress plugin for managing opinion columns at scale. It provides a complete editorial workflow for columnists and agencies, with built-in quality gates and SEO-optimized structured data.

= Key Features =

* **Custom Roles**: Columnista, Agencia, and Column Editor roles with granular capabilities
* **Editorial Workflow**: Draft, Submitted, In Review, Returned, Approved, Rejected, Published statuses
* **Quality Gates**: Automatic validation of word count (600-3000), title length, excerpt, featured image, and subheadings before submission
* **Agency Management**: Agencies can assign existing columnists and submit columns on their behalf
* **REST API**: Full API under `/wp-json/lean-columnas/v1/` for headless integration
* **SEO Schema Markup**: Automatic OpinionNewsArticle JSON-LD on column pages for E-E-A-T
* **Theme-Independent Templates**: Plugin templates that can be overridden from your theme
* **Performance First**: Custom tables for columnist and agency data, no unnecessary queries

= For Columnists =

Columnists can log in to WordPress, create columns, and submit them for editorial review. They can track the status of their submissions and respond to editorial feedback.

= For Agencies =

Agencies can be assigned existing columnists and submit columns on behalf of their roster. They have a dashboard view of all columns from their columnists.

= For Editors =

Column Editors have a dedicated editorial queue in wp-admin to review, approve, return with notes, or reject submitted columns. Quality gates ensure consistent content standards.

= REST API =

The plugin is API-first with endpoints for columnists, agencies, and columns management:

* `GET/POST /wp-json/lean-columnas/v1/columnists`
* `GET/POST /wp-json/lean-columnas/v1/agencies`
* `GET/POST /wp-json/lean-columnas/v1/columns`
* Workflow actions: submit, review, approve, return, reject, publish

= Template Override =

Override plugin templates by copying them to your theme:

* `your-theme/lean-columnas/single-columna-opinion.php`
* `your-theme/lean-columnas/archive-columna-opinion.php`
* `your-theme/lean-columnas/parts/column-card.php`

== Installation ==

1. Upload the `lean-columnas` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create custom roles and database tables
4. Go to Columnas in the admin menu to manage the editorial queue

== Frequently Asked Questions ==

= Can columnists create their own WordPress accounts? =

No, user accounts must be created by a WordPress administrator. Once the account exists and has the Columnista role, the columnist can log in and manage their columns.

= Can agencies create new columnist accounts? =

No. Agencies can only assign existing columnists to their roster. User account creation is handled by site administrators.

= What happens when I deactivate the plugin? =

Deactivation only flushes rewrite rules. All data (tables, roles, content) is preserved. Data is only removed when you delete the plugin through the WordPress admin.

= Does the plugin affect site performance? =

No. The plugin uses custom tables instead of post meta for columnist and agency data, and only loads its CSS on relevant pages.

== Changelog ==

= 0.1.0 =
* Initial release
* Custom post type: columna-opinion
* Custom taxonomy: columna-categoria
* Custom roles: Columnista, Agencia, Column Editor capabilities
* Editorial workflow with quality gates
* REST API for columnists, agencies, and columns
* OpinionNewsArticle schema markup
* Theme-overridable templates
* Admin editorial queue
