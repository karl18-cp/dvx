# Business inquiries

The landing page's **Send a business inquiry** button opens a local meeting-request modal. It collects contact/company details, service, message, preferred date/time, and timezone. The browser timezone is preselected and can be changed. The requested meeting must be in the future; dates are stored in UTC. Availability is confirmed directly with the client, not automatically booked.

Submissions are saved to `business_inquiries` before an email is attempted using the existing company SMTP configuration and `recruitment.inbox` (default `divertexcorp@gmail.com`). Reply-To is the client's email. No email is automatically sent to clients. Internal notes never appear in the company notification email.

Main administrators use **Management → Business Inquiries** to search/filter, review full details in a modal, update status/notes, and retry unsuccessful company emails. An email failure leaves the inquiry saved. Successful sends are not repeated by the retry action. A unique submission ID prevents duplicate records when the same form is submitted twice. Public submission and admin email retry routes are rate-limited.

Migration: `2026_09_26_030000_create_business_inquiries_table.php`.

Automated tests use fake email. Browser verification uses the isolated testing database with outbound mail disabled.
