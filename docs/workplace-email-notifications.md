# Employee email notifications

Production sends individual messages from `notifications@divertexcorp.com` to each active recipient's current account email. General replies go to the company Gmail inbox. Recipients are never placed together on a shared To/CC list.

## Coverage

- Announcements: active accounts.
- Sanctions: the affected employee and main administrators, following existing in-app visibility.
- Overtime and undertime: administrators awaiting review, then the requester for the decision.
- Leave: the assigned team leader for initial agent review, administrators after leader approval, and the requester for progress and final decisions.
- Attendance: existing late and possible-absence alerts for their authorized recipients.
- EOD reports: other main administrators.
- Task Tracker: the assignee for assignment and review outcomes; main administrators for submitted work awaiting review.
- Team forms, personal satisfaction ratings, and finalized call evaluations: relevant employees.
- Assessments, results, reminders, and coaching/training assignments: recipients of the existing assessment/coaching notification records.
- DiverText: an unread-message summary every 15 minutes, for messages at least five minutes old. Message text stays inside the portal. Read messages and conversations no longer accessible are excluded.
- Rankings: every Monday at 09:00 Asia/Manila for the previous Monday–Sunday. Agents receive their own earned points, cumulative position, and position change where available. Administrators receive the company top five; leaders receive only agents in their currently assigned teams. Trainee and QA accounts do not gain access to restricted ranking or messaging information through email.

## Activation and delivery

Run migrations, deploy all worker code, then set `WORKPLACE_EMAIL_ENABLED=true` and `WORKPLACE_EMAIL_START_AT` to the activation timestamp in UTC. Cache production configuration afterward. Defaults are disabled locally. The activation timestamp excludes historical alerts; it must not be reset on routine deployments.

The existing minute cron runs `workplace:email-notifications` and the database queue worker. Alerts generally enqueue within a minute, followed by queue processing. Weekly and chat commands are scheduled separately in `routes/console.php`.

`workplace_email_deliveries` retains a unique event key and delivery status, including after an in-app alert is removed. Normal repeats and retries do not resend completed deliveries. A queue worker crash after SMTP accepts mail but before the database commits can still cause a duplicate: SMTP does not provide an exactly-once delivery guarantee.

Recipients, active status, current team/role access, deleted alerts, and unread messages are rechecked at delivery time. Placeholder addresses (`.local`, `.test`, and example domains) are skipped. Set actual employee emails under Employees/Profile to receive messages.

Failed transport attempts retry up to three times. Inspect statuses without printing mail credentials; run `php artisan workplace:email-retry` after fixing a mail outage to reset only failed deliveries. `sent` means accepted by the configured transport, not guaranteed inbox placement.

No employee passwords, facial descriptors, or chat contents are included in these updates. Existing account welcome emails and recruitment/business inquiry email workflows remain separate.
