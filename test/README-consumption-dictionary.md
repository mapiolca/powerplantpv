# Consumption dictionary — PowerPlantPV 1.6.1 revision

The module declares `c_powerplantpv_connection_type` through its native descriptor dictionaries. It is a global reference: consumption modes are shared across entities. Plant `connection_type` and commercial/lead extrafield values keep their existing technical string codes. There is no document-value conversion or numeric-ID migration. Native `ExtraFields::update()` changes only module-owned original metadata to a code-keyed `sellist`; customized definitions remain untouched.

The activation seeds the four defaults once; a transverse initialization marker prevents later reactivation from restoring deleted defaults or overwriting edited labels/activation. No data is removed on module deactivation. Existing table/constant installation remains native. The About tab reads descriptor version 1.6.1.

## Executed checks

`php -d extension=mysqli -d extension=pdo_sqlite test/project_links_signed_totals.php /path/to/25-alpha/htdocs`

187 assertions on PHP 8.4.22 and cached Dolibarr 25.0.0-alpha sources: native objects, HookManager, ExtraFields rendering, isolated SQLite adapter and simulated extrafield installation manager. Covers the original project scenarios, aligned dictionary declarations, commercial and optional lead metadata, preservation of original values, custom/new/disabled labels, disabled saved selection, unmapped saved text and repeated initialization. Native selector markup is parsed with DOMXPath. This is not a MySQL activation or a deployed browser test.

Source review of official Dolibarr 20.0.0 confirms that `sellist` has varchar storage and allows a string `code` key. Its output lookup uses that code. Unlike the examined v25 sources, v20 has no `showInputExtraField` hook: `formObjectOptions` prepares native select metadata on cards to retain saved inactive choices. Rendering uses neutral metadata hooks only, without replacing native selectors. No core file or hook context is initialized inside a library.

PHP syntax and diff whitespace checked. PHPStan is unavailable in this checkout (no configured binary/configuration); no suppression or baseline added. Versions 20–24 and complete v25 instances remain unexecuted. Native dictionary CRUD, actual module activation, CSRF/rights and Multicompany browser acceptance remain manual. No ZIP installer validation or Dolistore submission is claimed.

## Manual acceptance

1. Update the module in each entity; verify the four defaults appear in Setup → Dictionaries.
2. Edit a label, add a code, disable a used code; verify plant and commercial/optional lead forms, list filters and PDF labels. A new record must not offer the disabled code; an existing card must keep its saved value.
3. Disable/reactivate PowerPlantPV; verify edited labels, disabled/deleted defaults and all stored plant/document values persist. Verify the dictionary is shared while business data permissions and entity restrictions remain unchanged.
4. Use native dictionary admin permissions and CSRF checks. Keep codes stable: change labels or disable entries rather than renaming/deleting used codes.

The published 1.6.1 tag remains immutable. A corrected source archive, identified by commit and SHA-256 in the release notes, can be attached without presenting it as an installer-validated Dolibarr package.

Dynamic LMDB Lead dictionary choices require Dolibarr 24+ and its native `completeFetchNameOptionalsLabel` hook. The module adapts only its own dictionary-bound definition to a native plain select in memory, matching LMDB Lead's accepted types. On Dolibarr 20–23 the existing lead select remains intact; the dynamic capability is reported unavailable in Compatibility. In the initial v20 core, filtered sellists produce an invalid `AND` without `WHERE`; the module uses the native unfiltered sellist definition there and prepares active card choices before rendering. Other supported versions keep the native USF filter.
