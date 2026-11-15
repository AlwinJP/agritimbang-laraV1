AgriTimbang — Farmer Transactions Without Buyer Confirmation

This overlay updates the existing combined Authority/Follow-up overlay's review rules.

Changes:
- Farmer-created transactions no longer require buyer confirmation before LGU Authority approval or rejection.
- Farmer follow-up reported sales continue directly to Authority review.
- Legacy farmer transactions still marked `pending` are included in the Authority review queue and can be normalized when the Authority processes them.
- LGU Encoder and auction-house verification rules remain separate and unchanged.

Install:
1. Extract this ZIP into the AgriTimbang project root and overwrite matching files.
2. Run: php artisan optimize:clear
3. Run: npm run build

No migration is required. Existing buyer confirmation records are not deleted; they are simply not required for farmer transaction verification.
