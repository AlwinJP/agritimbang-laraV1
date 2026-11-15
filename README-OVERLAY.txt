AgriTimbang - Farmer Auction Follow-up Overlay

Files in this ZIP are only new or modified files. Extract into the project root,
keeping the directory structure.

Install:
  php artisan migrate
  php artisan optimize:clear
  npm run build

For automatic auction follow-up generation, notifications, reminders, and aging
statuses in local Laragon development, keep Laravel's scheduler running in a
separate terminal:
  php artisan schedule:work

The scheduler also runs the existing auction status synchronization command.

Follow-up schedule:
  Day 1: Initial farmer notification
  Day 3: Reminder
  Day 7: Final reminder
  Day 14: Mark overdue for LGU monitoring
  Day 30: Mark unresponsive; stop automated reminders

No response never marks livestock as sold or changes livestock availability.
If the farmer reports a sale, the system checks for an existing active/verified
transaction first. If none exists, it submits an auction-linked transaction for
LGU Authority verification. Buyer details remain optional.

The uploaded source ZIP did not include vendor/ or node_modules/, so full Laravel
runtime/database tests and a Vite production build could not be run here.
