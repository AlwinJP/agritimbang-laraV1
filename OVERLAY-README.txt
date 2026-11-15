AgriTimbang Farmer Transaction Recording UI Overlay

This overlay replaces the Farmer's Record Transaction screen with a guided 4-step flow:
1. Select livestock, enter each animal's actual selling price, and choose Slaughter or Breeding.
2. Enter sale date and actual selling location (separate from farmer residence).
3. Optionally provide buyer name/mobile and remarks.
4. Review and submit.

Backend behavior:
- Buyer name and mobile are optional for Farmer and Encoder submissions.
- Existing Encoder transaction entry remains compatible; missing sale purpose falls back to the livestock record purpose (or Slaughter if unavailable).
- A saved FPC valuation is no longer required to record a sale. If one exists, it is captured automatically; otherwise the sale is still recorded and per-kg/reference comparison values remain unavailable until supported data exists.
- Farmer submissions go directly to pending_lgu_verification; no buyer confirmation SMS is issued.
- Sale purpose is stored per transaction item and copied to the livestock record.
- Transaction date cannot be in the future.

Install:
1. Extract the ZIP into the Laravel project root, preserving folders.
2. Run: php artisan migrate
3. Run: php artisan optimize:clear
4. Run: npm run build

No seeder is required. This overlay does not change the Public Auction Dashboard or Encoder transaction UI.

Validation performed: PHP syntax checks and Node JavaScript syntax check passed. A Vite build and Laravel feature tests were not run because node_modules and vendor/bin/phpunit were not present in the supplied ZIP workspace.
