# Consumption type and proposal module counting — 1.6.0

The optional text field `powerplantpv_consumption_type` is initialized on `propal`, `commande` and `facture` with the same key, label and size (255 characters). Existing definitions are never rewritten. It is not a parallel consumption dictionary or a retroactive synchronization service.

## Executed locally

- PHP syntax and PHPStan level 5 / PHP 8.0 target for the new counting API, using the native core bootstrap; no ignore or baseline added.
- `php -d extension=pdo_sqlite test/proposal_modules.php`: real SQLite counting SQL for repeated products, signed quantities, MODULE classification, authorized product/proposal entities, missing rights, native-access denial and read failure. The access context is simulated. Queries are checked to be reads only.
- `php -d extension=pdo_sqlite test/project_links_signed_totals.php /path/to/dolibarr/htdocs`: 100 checks, including seven consumption-field initialization checks with simulated field-manager replies (all three targets, repeat activation, customization and deliberately empty values, initialization failure). Native project/link tests and SQLite SQL remain included.
- Local core: `0ac96524bf68b063a44d6af8137e05bf11c227c8`, Dolibarr 25.0.0-alpha; PHP 8.4.22. These are not tests of a final v25 release or of every supported PHP combination.

The existing six-version CI matrix (Dolibarr 20–25 alpha) runs these scenarios. A configured or pending job is not a successful result; inspect the PR head SHA.

## Remaining real-instance checks

Through the integrated browser after explicit deployment: activation/update and reactivation preserve settings and values, the field is editable on all three native forms, and native proposal → order → invoice conversions carry it forward. The current core source transfers matching extrafield keys in `Commande::createFromProposal` and invoice conversion methods; this source inspection does not prove a deployed end-to-end conversion. Check manual destination choices and separate entity configurations.

No existing document is rewritten. No schema purge, permission renumbering, automatic module activation, deployment, merge or new release is performed by this PR. The prior 1.5.0 release/tag was verified on `1a1ddab90785f5812dc52f6724693b725fb14111` before preparing 1.6.0. Source review follows the official Dolistore packaging rules (wiki oldid 65313, consulted 2026-11-05); no installable ZIP or Dolistore acceptance is claimed.
