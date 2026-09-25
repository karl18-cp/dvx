# Applicant portal and recruitment inbox

Public form: `/careers` (no account needed). Applicants submit their name, email, phone, position, location, experience and an optional PDF/DOC/DOCX résumé up to 5 MB. Files stay on the private local disk.

Progress portal: `/applicant-portal` (no employee account needed). Applicants enter their submitted email and phone plus a one-use verification answer. The latest matching application shows its current stage, public recruitment update, submitted date and last update date. The successful lookup response is not cacheable. Internal notes, résumé paths and employee data are not returned. Use Update Visible to Applicant in the admin review modal to publish instructions; applicants can refresh their lookup to see changes.

Admin navigation: Management → Applicants. Existing manager access is retained. Review opens a modal; statuses are For Screening, For Final Interview, Passed, and Failed. Internal notes remain private; the separate applicant update appears in the existing public status lookup.

Reviews for Screening and Final Interview require an interview start date/time; Passed requires a training start date/time. Enter Philippine time (Asia/Manila); the database stores UTC and the email/portal display Philippine time. Changing the stage clears the form’s previous date to require a deliberate new schedule. Changing only the schedule also notifies the applicant. Failed requires no schedule and clears the current scheduled start. Newly submitted applications may remain For Screening without a schedule until an administrator reviews them.

Saving a changed stage or applicant-visible message sends a status notification to the applicant’s stored email using the configured company sender. The message includes only their name, position, new stage, public update and portal link. Unchanged saves and internal-note-only edits do not send duplicates. If delivery fails, the saved review remains visible in the portal and the applicant row shows an email-pending notice; saving the review again retries the latest update. The recruitment-inbox email retry remains separate.

New submissions are saved before an email is attempted. The message includes the contact information and résumé attachment, with Reply-To set to the applicant. Email failure does not lose the application. Admins can retry unsent messages; already-sent applications are not sent again. “Accepted by mail server” means SMTP accepted the message, not that inbox placement was verified. Sending is synchronous with a 15-second SMTP timeout; no queue worker is required.

Gmail setup in the untracked `.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=divertexcorp@gmail.com
MAIL_PASSWORD="your-google-app-password"
MAIL_FROM_ADDRESS=divertexcorp@gmail.com
MAIL_FROM_NAME="Divertex"
RECRUITMENT_INBOX=divertexcorp@gmail.com
```

Use a Google App Password for the sending account (2-Step Verification required), not its normal password. If another company Gmail account sends the messages, change MAIL_USERNAME and MAIL_FROM_ADDRESS together. Keep RECRUITMENT_INBOX as the recruitment recipient. Never commit credentials.

After editing `.env`, run `php artisan config:clear`. Submit a real application or use Send email on an existing application, then confirm receipt in the recruitment mailbox. Automated tests use a fake mailer and do not send real messages.

Google instructions: https://support.google.com/mail/answer/185833 and https://support.google.com/mail/answer/7104828
