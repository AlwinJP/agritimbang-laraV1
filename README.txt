AgriTimbang - Farmer Follow-ups Layout Fix

This overlay contains only the updated stylesheet:
resources/css/auction-follow-ups.css

It scopes the follow-up page to the available main content column, resets accidental horizontal offsets/transforms, and ensures the summary cards, monitoring panel, filters, and follow-up records use the available width. The summary cards collapse to two columns on narrower screens and one column on very narrow screens.

Installation:
1. Extract this ZIP into the AgriTimbang project root and overwrite the matching file.
2. Run:
   php artisan optimize:clear
   npm run build
3. Hard refresh the browser with Ctrl + F5.

No migration or seeder is required. This is a CSS-only layout correction; it does not change follow-up records, notifications, transaction logic, or Authority verification.
