# POWERPLANTPV FOR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

## Features

- Manage photovoltaic power plants in Dolibarr.
- Track the material composition of a power plant by PV product category.
- Import PV Free technical data into existing Dolibarr PV module and inverter products from the detailed characteristics tab.
- Import CSV/XLSX technical characteristics into existing Dolibarr PV module, inverter and battery products from the detailed characteristics tab, with unit-aware downloadable templates, preview and source traceability.
- Describe batteries, storage systems and battery accessories with constraint-ready technical data, normalized communication/protection/certification relations, and native Dolibarr kit compositions.
- Calculate usable storage capacity on proposals, orders and invoices, including recursively nested native product kits.
- Import CSV/XLSX serial numbers by composition category, validate product-line associations, and store each serial number against the power plant, composition line, product and PV category.
- Export recorded serial numbers as CSV or XLSX.
- Manage PowerPlantPV attestations for dynamic inverter curtailment, static inverter curtailment, maximum frequency 51.5 Hz, and installer under 100 kWc workflows.
- Generate attestation PDF skeletons from power plant, site, installer, writer and equipment data. Online signature uses Dolibarr's native `/public/onlinesign/newonlinesign.php` page when the installed core explicitly supports the `powerplantpv_attestation` source, otherwise it falls back to the module public signature page.
- Prepare the preventive maintenance data foundation with native dictionaries, rights and extra fields on contracts, products/services and interventions.
- Configure intervention report templates for maintenance: report models, sections, fields, select options, service-to-section mappings, and intervention nature to model association.
- Fill intervention reports from the `Rapport` tab, with a generated snapshot based on the intervention nature, active services, linked power plants and the configured report template.
- Generate dynamic intervention PDFs from the saved maintenance report snapshot with the `powerplantpvreport` Fichinter document model.
- Track production and consumption readings from power plant cards or finalized intervention reports.

<!--
![Screenshot powerplantpv](img/screenshot_powerplantpv.png?raw=true "PowerPlantPV"){imgmd}
-->

Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Translations

Translations can be completed manually by editing files in the module directories under `langs`.

<!--
This module contains also a sample configuration for Transifex, under the hidden directory [.tx](.tx), so it is possible to manage translation using this service.

For more information, see the [translator's documentation](https://wiki.dolibarr.org/index.php/Translator_documentation).

There is a [Transifex project](https://transifex.com/projects/p/dolibarr-module-template) for this module.
-->


## Installation

Prerequisites: You must have Dolibarr ERP & CRM software installed. You can download it from [Dolistore.org](https://www.dolibarr.org).
You can also get a ready-to-use instance in the cloud from https://saas.dolibarr.org


### From the ZIP file and GUI interface

If the module is a ready-to-deploy zip file, so with a name `module_xxx-version.zip` (e.g., when downloading it from a marketplace like [Dolistore](https://www.dolistore.com)),
go to menu `Home> Setup> Modules> Deploy external module` and upload the zip file.

<!--

Note: If this screen tells you that there is no "custom" directory, check that your setup is correct:

- In your Dolibarr installation directory, edit the `htdocs/conf/conf.php` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading `//`) and assign the proper value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```
-->

<!--

### From a GIT repository

Clone the repository in `$dolibarr_main_document_root_alt/powerplantpv`

```shell
cd ....../custom
git clone git@github.com:gitlogin/powerplantpv.git powerplantpv
```

-->

### Final steps

Using your browser:

  - Log into Dolibarr as a super-administrator
  - Go to "Setup"> "Modules"
  - You should now be able to find and enable the module

## Attestations

The attestation feature is enabled from the module settings tab `Attestations`. Settings are stored per entity and include frequency and curtailment defaults, per-type PDF model selection, and the company PNG stamp. Every attestation must be linked to a PV power plant. Place and installer data are read from the Dolibarr MyCompany information of the attestation entity, site data is read from the linked PV power plant, and writer data is read from the native author user (`fk_user_creat`).

Attestations use native Dolibarr rights, menus, document generation, file storage, Agenda triggers, Notifications support, and Multicompany sharing. The signature link uses Dolibarr's native online signature URL pattern when the installed core supports the `powerplantpv_attestation` source in `/public/onlinesign/newonlinesign.php` and `/core/ajax/onlineSign.php`. When the core does not support this source, PowerPlantPV exposes its own public fallback page with the same visual and functional flow, secured by `ref`, `entity` and `securekey`. It is not a qualified external e-signature provider workflow.

Signed attestations remain locked for standard write/delete users. Grant the specific `powerplantpv / attestation / manage_signed` right, together with read access, to allow modification, deletion and PDF regeneration of signed attestations.

## Maintenance v1.3

Version 1.3.0 stabilizes the preventive maintenance workflow. It adds entity-aware dictionaries, report templates, service-to-section mappings, contract/product/intervention extra fields, generated report snapshots, production/consumption readings, dynamic intervention PDF generation, and global maintenance list/calendar/statistics pages.

The Maintenance menu now opens a dedicated operational dashboard. Its widgets can be added, removed and reordered in two columns, with a layout stored independently for each user and entity. Widget contents use native Dolibarr box rows and each dashboard widget provides translated contextual help. The same catalog is available as optional native Dolibarr home boxes; maintenance boxes are never enabled on home pages by default.

The maintenance statistics page is a fixed comparative analysis based on dated intervention records. It overlays two or three calendar years for monthly intervention and completion volumes, and compares annual totals and breakdowns by intervention nature, power plant and customer. The current completion status is used; no historical end-of-year status is reconstructed.

Prerequisites:

- Dolibarr v20.0 or higher.
- PHP 8.0 or higher.
- Native contracts and interventions enabled for the operational workflow.
- PowerPlantPV rights assigned to the target users.

## Batteries and storage systems

Assign the `BATTER` photovoltaic category to a battery or storage product and complete its detailed characteristics. The supported classifications are battery module, DC system, AC-coupled all-in-one system and hybrid all-in-one system. All-in-one products reuse the inverter, AC, MPPT and EPS characteristics stored by PowerPlantPV; native Dolibarr product dimensions and weight remain the source of truth.

Multi-capacity ranges are represented with native Dolibarr product kits: create one kit for each commercial capacity and compose it from battery modules and accessories. PowerPlantPV recursively expands nested kits, multiplies quantities at every level and totals only terminal `BATTER` components. It deliberately does not aggregate voltage, current or power across a kit. Cyclic compositions are reported as incomplete.

The calculated `Storage capacity (kWh)` extra field is maintained on proposals, orders and invoices. A document without a battery stores `0`; a document containing a battery without usable capacity stores an empty value and displays a non-blocking warning. Administrators can recalculate all accessible documents from the module settings.

Assign `BATACC` to battery accessories. Their controlled role and normalized compatibility, requirement or incompatibility rules can target a product, family, brand, storage type, chemistry, protocol, capacity range or module count.

CSV and XLSX templates state the expected unit or format in every header. Older headers without a suffix remain accepted with a warning, while a contradictory unit is rejected. Battery templates accept repeated protocol, protection and certification columns using `CODE` or `CODE|Label`; kits and accessory rules are intentionally excluded from this flat import.

Quick overview:

1. Administrators configure maintenance services, report templates, sections, fields and intervention natures.
2. A Dolibarr service is tagged with one or more PowerPlantPV maintenance services.
3. A contract service is linked to a photovoltaic power plant and receives the maintenance period.
4. A maintenance intervention is created from a power plant, the maintenance list or the calendar.
5. The intervention `Rapport` tab creates a frozen snapshot on first save.
6. The `powerplantpvreport` Fichinter PDF model renders the saved report without creating or recalculating a snapshot.
7. Production/consumption readings can be entered manually or synchronized from finalized reports.

Documentation:

- [User guide v1.3 Maintenance](docs/user/v1.3-maintenance-user-guide.md)
- [Administrator guide v1.3 Maintenance](docs/admin/v1.3-maintenance-admin-guide.md)
- [Technical overview v1.3 Maintenance](docs/technical/v1.3-maintenance-technical-overview.md)
- [Release checklist v1.3 Maintenance](docs/release/v1.3-maintenance-release-checklist.md)

Known limits:

- The module does not create maintenance interventions automatically.
- The maintenance planning is calculated from source data and is not persisted as a cache.
- The PDF model reads the report snapshot; it does not create or recalculate it.
- No dedicated cron job is added for v1.3 Maintenance.



## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readme's are licensed under [GFDL](https://www.gnu.org/licenses/fdl-1.3.en.html).
