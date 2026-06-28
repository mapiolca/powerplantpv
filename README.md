# POWERPLANTPV FOR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

## Features

- Manage photovoltaic power plants in Dolibarr.
- Track the material composition of a power plant by PV product category.
- Import PV Free technical data into existing Dolibarr PV module and inverter products from the detailed characteristics tab.
- Import CSV/XLSX technical characteristics into existing Dolibarr PV module and inverter products from the detailed characteristics tab, with downloadable templates, preview and source traceability.
- Import CSV/XLSX serial numbers by composition category, validate product-line associations, and store each serial number against the power plant, composition line, product and PV category.
- Export recorded serial numbers as CSV or XLSX.
- Manage PowerPlantPV attestations for dynamic inverter curtailment, static inverter curtailment, maximum frequency 51.5 Hz, and installer under 100 kWc workflows.
- Generate attestation PDF skeletons from power plant, site, installer, writer and equipment data. Online signature uses Dolibarr's native `/public/onlinesign/newonlinesign.php` page when the installed core explicitly supports the `powerplantpv_attestation` source, otherwise it falls back to the module public signature page.
- Prepare the preventive maintenance data foundation with native dictionaries, rights and extra fields on contracts, products/services and interventions.
- Configure intervention report templates for maintenance: report models, sections, fields, select options, service-to-section mappings, and intervention nature to model association.

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

## Maintenance foundation

Version 1.3.0 introduces the data foundation for preventive maintenance: entity-aware dictionaries, service-to-section mappings, report template field definitions, rights and extra fields. The power plant card now includes a `Maintenance` tab that reads linked contracts and interventions, displays active maintenance services, and calculates the next maintenance status from the explicitly configured contract period.

The report template engine turns preventive maintenance into a preinstalled configurable model named `preventive_maintenance`. Administrators can create, edit, disable, duplicate and reorder report templates, sections, fields, field options, service mappings and intervention nature associations from the PowerPlantPV settings tabs. The intervention Report tab and PDF generation are not implemented in this step.

The PR4 maintenance tab does not add the global Maintenance menu, the global calendar, automatic recurrence date generation, or maintenance PDF generation.



## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readme's are licensed under [GFDL](https://www.gnu.org/licenses/fdl-1.3.en.html).
