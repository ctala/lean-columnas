# Changelog

All notable changes to the Lean Columnas plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-03-25

### Added

- Custom post type `columna-opinion` with archive support and REST API integration.
- Custom taxonomy `columna-categoria` for column categorization.
- Custom roles: `lc_columnista` and `lc_agencia` with granular capabilities.
- Column Editor capabilities added to existing `editor` and `administrator` roles.
- Custom database tables `lc_columnists` and `lc_agencies` for performance at scale.
- Editorial workflow with statuses: Draft, Submitted, In Review, Returned, Approved, Rejected, Published.
- Quality gates enforced before submission: word count (600-3000), title length (10-70 chars), excerpt required, featured image required, minimum 2 subheadings, no script/iframe tags.
- REST API under `/wp-json/lean-columnas/v1/` with endpoints for columnists, agencies, and columns.
- Workflow action endpoints: submit, review, approve, return (with notes), reject (with notes), publish.
- Agency-columnist assignment system with max_columnists limit enforcement.
- OpinionNewsArticle JSON-LD schema markup on single column pages.
- Theme-overridable templates: single, archive, and column card partial.
- Admin editorial queue page with status tabs and workflow action buttons.
- Admin pages for columnist and agency management.
- Frontend CSS with responsive grid layout for archives.
- Plugin activation: automatic table creation and role registration.
- Plugin uninstall: clean removal of all tables, roles, capabilities, and options.
