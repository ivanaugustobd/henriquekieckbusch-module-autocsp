# HenriqueKieckbusch_AutoCSP

## Overview

This Magento 2 module automates the management of CSP whitelists and can dynamically capture CSP violations, add them to a database, and manage inline script CSP via nonce.

## Features

- Automatically capture and store CSP violation reports.
- Add collected CSP policies from DB to CSP headers.
- Capture mode: enable `report-uri` and `report-only`.
- Inline scripts support: auto-add nonce to `<script>` tags lacking one, and add the nonce to `script-src`.

## Installation

1. Place under `app/code/HenriqueKieckbusch/AutoCSP/`.
2. `bin/magento setup:upgrade`
3. `bin/magento cache:flush`
4. Configure in Admin: Stores > Configuration > Security > Auto CSP.

## Usage

- Enable module and capture mode to populate DB.
- Disable capture mode when done.
- Enable inline script CSP if needed, ensuring inline scripts get a nonce.
