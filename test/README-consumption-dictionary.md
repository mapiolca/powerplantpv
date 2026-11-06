# Consumption dictionary — PowerPlantPV 1.6.1

`powerplantpv_consumption_type` is initialized as a native `select` on `propal`, `commande`, `facture` and, optionally, `lmdblead_lead`. Its four options come directly from `PowerPlant::getConnectionTypeOptions()`; translation keys are stored as labels, technical keys as values. No independent connection dictionary, historical choices or data conversion is introduced.

Lead initialization requires LMDB Lead to be active and `LMDBLEAD_EXTRAFIELDS_SCHEMA = 1`, matching its native extrafields storage contract. PowerPlantPV does not install lead tables or modify LMDB Lead code, permissions or revision controls. Activate/update LMDB Lead first, then update PowerPlantPV in each relevant entity. The Compatibilité page reports this dependency. Lead-to-proposal propagation is not added by this change.

Existing customized definitions are deliberately preserved on repeat initialization. The original module-owned text definition is upgraded with `ExtraFields::update()`, preserving metadata, while the exact lost-quote activation condition observed in the native editor is repaired. No historical choices are added and no document values, rights or business records are rewritten. The user explicitly excluded historical value conversion from this patch.

## Checks

`php -d extension=pdo_sqlite test/project_links_signed_totals.php /path/to/dolibarr/htdocs`

- 160 checks on v25 with native objects/helpers, HookManager and SQLite SQL; the extrafield installation manager is simulated (157–160 depending on metadata supported by the core revision).
- All three commercial targets use exactly the connection dictionary; installed leads receive the same field. Absent leads and an incomplete schema are skipped.
- Entity IDs 1 and 2, repeat initialization, deliberately empty values and customized definitions are preserved; installation errors are reported.
- Real native ExtraFields input/output rendering covers all four targets and all four values, empty output and French/English labels. Native selectors retain technical keys.
- Original text-definition upgrade, settings/owner/CSS preservation, version-dependent AI/clone/tooltip/personal-data metadata, repeat activation, unchanged business value and failure of the metadata upgrade.

Local execution: PHP 8.4.22, Dolibarr 25.0.0-alpha sources `0ac96524bf68b063a44d6af8137e05bf11c227c8`. Local caches 20–24 are incomplete and cannot run the complete scenario; the existing six-version CI uses complete checkouts and must be checked on the published SHA. This is not six full ERP instances or a real LMDB Lead browser acceptance.

Syntax and diff checks pass. PHPStan level 5 / PHP 8.0 target, native bootstrap, on the descriptor and compatibility class: 28 pre-existing diagnostics, identical to main by message/identifier; no new diagnostic, baseline or ignore. The proposal module count regression also passes (12 assertions).

JPSUN 1.25.1 resolves the consumption key through `PowerPlant::getConnectionTypeLabel()` before printing its cover, including output language handling. This uses the resolver already present in PowerPlantPV 1.6.0, so the JPSUN tests pin that published source.

The published stable 1.6.0 release and tag resolve to `57cb7bcd563ac83ae3ae00053f63621583601764`, verified before this increment. No deployment, native installer validation or Dolistore acceptance is claimed for 1.6.1. The previous 1.6.0 acceptance report remains historical evidence only.
