# Changelog

All notable changes to the Lean Columnas plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-03-26

### Added

- Email notifications on editorial workflow transitions (submitted, in review, approved, returned, rejected, published).
- Filterable notifications via `lean_columnas_send_notification` filter.
- Dashboard widget "Mis Columnas" for columnists showing column counts by status and recent submissions.
- Dashboard widget "Cola Editorial" for editors showing pending review counts and weekly stats.

### Fixed

- Updated readme.txt and CHANGELOG to reflect user meta architecture (no custom tables).

## [0.1.0] - 2026-03-25

### Added

- Custom post type `columna-opinion` with archive support and REST API integration.
- Custom taxonomy `columna-categoria` for column categorization.
- Custom roles: `lc_columnista` and `lc_agencia` with granular capabilities.
- Column Editor capabilities added to existing `editor` and `administrator` roles.
- Columnist and agency data stored in WordPress user meta (no custom tables).
- Editorial workflow with statuses: Draft, Submitted, In Review, Returned, Approved, Rejected, Published.
- Quality gates enforced before submission: word count (600-3000), title length (10-70 chars), excerpt required, featured image required, minimum 2 subheadings, no script/iframe tags.
- Agency-columnist assignment via user meta `lc_agency_user_id`.
- OpinionNewsArticle JSON-LD schema markup on single column pages.
- Theme-overridable templates: single, archive, and column card partial.
- Admin editorial queue page with status tabs and workflow action buttons.
- Admin pages for columnist and agency management.
- Frontend CSS with responsive grid layout for archives.
- Plugin activation: automatic table creation and role registration.
- Plugin uninstall: clean removal of all tables, roles, capabilities, and options.
