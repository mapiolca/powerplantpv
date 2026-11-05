# Consumption type and proposal module counting — 1.6.0

The optional text field `powerplantpv_consumption_type` is initialized on `propal`, `commande` and `facture` with the same key, label and size (255 characters). Existing definitions are never rewritten. It is not a parallel consumption dictionary or a retroactive synchronization service.

## Executed locally

- PHP syntax and PHPStan level 5 / PHP 8.0 target for the new counting API, using the native core bootstrap; no ignore or baseline added.
- `php -d extension=pdo_sqlite test/proposal_modules.php`: real SQLite counting SQL for repeated products, signed quantities, MODULE classification, authorized product/proposal entities, missing rights, native-access denial and read failure. The access context is simulated. Queries are checked to be reads only.
- `php -d extension=pdo_sqlite test/project_links_signed_totals.php /path/to/dolibarr/htdocs`: 100 checks, including seven consumption-field initialization checks with simulated field-manager replies (all three targets, repeat activation, customization and deliberately empty values, initialization failure). Native project/link tests and SQLite SQL remain included.
- Local core: `0ac96524bf68b063a44d6af8137e05bf11c227c8`, Dolibarr 25.0.0-alpha; PHP 8.4.22. These are not tests of a final v25 release or of every supported PHP combination.

The existing six-version CI matrix (Dolibarr 20–25 alpha) runs these scenarios. A configured or pending job is not a successful result; inspect the PR head SHA.

## Remaining real-instance checks

Integrated-browser acceptance on develop, 2026-11-05: Dolibarr 25.0.0-alpha / PHP 8.3.36, TEST 1, Codex account; PowerPlantPV `7f55869d6faf2143592d19adfad82f4917772c53`, JPSUN `8cae584480fb7ce2bdc6a2a631507ec0d60d1ce8`, checked out through cPanel Git Version Control. Both modules reloaded through native administration. Consumption text was saved and read back on proposal 53, order 72 and invoice 117; invoice creation with the field empty succeeded. All three values survived a second native module reload. Two lines of the same fictitious 450 Wc module (2 + 3) yielded 2.25 kWc, and one fictitious battery yielded 10 kWh; the JPSUN cover read these values and displayed five modules. No commercial validation, signature or sending was performed. The native zoning extension reported `GeocoderHttpError` on proposal/invoice creation, while both drafts persisted.

The three fictitious drafts and added extrafields are retained. Temporary JPSUN color/model changes were restored; the document default was unchanged. Develop is restored to its previous code after acceptance. Screenshots and downloaded PDFs remain local and are not package assets. Native conversions, restricted user profiles and authorized shared documents remain unverified on the instance.

Restoration verified in cPanel and the served About pages: PowerPlantPV 1.5.0 at `f067f003d16963cbe09a535eabea549c467a5c1e`, using `fix/recette-restore-develop-20261105` because the original remote branch was deleted; JPSUN 1.24.2 at `850bd90f4b9263e637042f09a8f44dd60be3332b` on its original branch. No merge or new release.

Still required through the integrated browser on the served PR SHA: complete deactivation/reactivation, native proposal → order → invoice conversions, manual destination choices and separate entity configurations. The current core source transfers matching extrafield keys in `Commande::createFromProposal` and invoice conversion methods; source inspection does not prove a deployed end-to-end conversion.

No existing commercial document is rewritten by the feature. No schema purge, permission renumbering, automatic module activation, merge or new release is performed by this PR. The prior 1.5.0 release/tag was verified on `1a1ddab90785f5812dc52f6724693b725fb14111` before preparing 1.6.0. Source review follows the official Dolistore packaging rules (wiki oldid 65313, consulted 2026-11-05); no installable ZIP or Dolistore acceptance is claimed.
