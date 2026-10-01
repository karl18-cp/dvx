# Accounting portal

Admins can open **Accounting** from the sidebar. Set an employee's position to **Accounting** in employee management to grant the dedicated portal. Accounting accounts land here after login and can also access Attendance, their own settings and notifications; other operational modules are blocked server-side. Agents, team leaders, trainees, managers and QA admins cannot access accounting data.

Accounting users navigate separate sidebar pages for Payment register, Training allowances, Expenses, Bank information, Invoices, Receivables and Payables. Each page has its own URL and supports direct loading, refresh and browser history. Admins retain all seven sections as tabs within their Accounting page. The underlying records and permissions are shared by both layouts.

## Payroll

### Attendance corrections

Admins and Accounting can edit all employees' attendance. Team leaders can edit agents and trainees belonging to their assigned teams, including multiple teams, but cannot correct their own attendance or other leaders. These permissions are enforced on both correction endpoints. Accounting has a dedicated Attendance sidebar link.

Correct individual time-in, break and time-out entries, or use **Edit hours** in the Total Hours column. A manual daily total is net of unpaid breaks; optional overtime and night portions cannot exceed the total. The overtime portion is subtracted from basic hours before applying the overtime rate. Hours are rounded to the nearest minute. A reason is required; actor, reason, and before/after values are audited. Paid-leave totals remain leave credit, and unpaid leave cannot receive positive credit through this correction. Manual totals override calculated attendance until removed. Restoring calculated hours returns to time entries and approved requests; saving an individual time correction also clears the daily manual override. Payroll uses the corrected values and its existing stale-draft checks still apply. Recorded payments are not retroactively changed.

New payroll drafts follow the supplied payslip workbook. Select the payout month and either the 15th payout (previous month's 26th through this month's 10th) or month-end payout (11th through 25th). Month-end payday is the final calendar day, including February. Scheduled payday is saved separately from the actual payment date.

Accounting enters regular and basic hourly rates and fixed allowances. Hours are read-only and come from the employee's credited attendance within the cutoff, grouped by the attendance shift date (including overnight shifts ending after the cutoff). The same attendance calculation applies saved schedules, approved requests, lateness, undertime and unpaid breaks. Approved paid leave adds scheduled net hours; unpaid leave and incomplete punches add no hours. Incomplete punches produce a warning. Approved worked overtime is separated from basic and variable allowance hours to avoid paying it twice. Night differential covers worked time from 10 PM to 6 AM, excluding breaks and leave. Accounting selects regular and special non-working holiday shift dates; premium hours come from worked attendance on those dates. Rest/special-day hours share one premium and are not duplicated.

Variable allowance rate is regular rate minus basic rate minus 10% of basic rate. Other component rates are respectively basic × 10%, × 125%, × 30%, and × 100%, matching the workbook. Rates retain fractional centavos, and exact attendance minutes are used until each earnings line is rounded to centavos. These are the supplied workbook rules; the system does not infer additional statutory, holiday, or tax rules.

SSS, PhilHealth, Pag-IBIG and other deductions are entered by accounting for the month-end payout only. Other deductions require an explanation. The 15th payout has zero deductions, enforced server-side. Saved drafts retain the rates, attendance source, hours, earnings, deductions and scheduled payday. Client-supplied hours and totals are ignored. New manual payroll submissions are rejected. Before approval or payment, attendance is recalculated and compared with the snapshot; changed records require voiding the unpaid draft and preparing it again. Earlier unpaid manual-hour drafts must also be recreated, while paid history remains unchanged. Net pay must be positive; overlapping non-void payroll periods remain rejected.

Review the draft, approve it, then record an actual payment's date, method and reference. Recording a payment does not transfer money. Unpaid drafts or approvals can be voided with a reason and replaced; recorded payments are read-only. Approval is a separate action but does not require a different staff member.

## Training allowances

Choose an enrolled trainee and attendance cutoff. The existing training plan determines attended days, phase rates and first payout threshold. The first draft includes the first configured number of attended days. Once it is recorded as paid, subsequent drafts include remaining eligible unclaimed days up to the selected cutoff.

Drafts reserve attendance dates; a unique database constraint prevents duplicate claims. Approval and payment recheck eligibility and rates. If the source changed, void the unpaid entry and prepare it again. Paid entries retain their original amount and source snapshot.

The register separates draft, approved and paid totals. Audit logs retain actors and entry snapshots. Account deletion is blocked when financial records refer to that account.

Recording payment creates an in-app notification for the recipient and enters the existing employee email-notification workflow. Recipients do not receive access to other employees' accounting records.

## My Payslips

Employee portals, including admin, team leader, accounting and QA admin, have a **My Payslips** sidebar page. Only the signed-in employee's paid payroll entries are listed; drafts, approved-but-unpaid entries, voids and training allowances are excluded. The breakdown opens in a modal with an X close button and shows saved earnings, deductions, net pay, payment details and the attendance used for that payment. Older manual payroll records show their saved totals without inventing an itemized breakdown.

Both list and detail queries enforce the current user's ownership, including for admin/accounting users on this personal page. Other users' entries return 404. Personal responses use private/no-store caching and omit internal payroll notes, approval/audit metadata and manual correction reasons. New payroll payment notifications link to the employee's payslip. Recorded payment snapshots are not recalculated when attendance changes later.

## Expenses

The Expenses tab lets admins and Accounting users create and rename categories, then record business expenses with a date, category, description, PHP amount, payee, receipt/invoice reference and notes. Date and category filters control the list and filtered total; category cards show spending across all categories within the selected date range. Expenses are separate from payroll and training allowance totals.

Duplicate category names are rejected regardless of case or surrounding whitespace. Rename keeps existing expenses attached to the same category. Incorrect expenses can be voided with a reason; they remain visible in history but are excluded from totals. Voiding does not reverse a payment. Creation, category edits and voids are audited. Expense submit keys prevent duplicate records on retries.

## Employee bank information

Admins and Accounting users can add, edit and delete multiple bank records per employee (including trainees). Each record identifies the employee, Payee/Payor/Both role, bank, optional branch, account holder, account number and optional notes. Payee means receiving payments; payor means making payments. These records do not initiate payments or change previous payroll entries.

Account holder, number and notes are encrypted with the application encryption key. Preserve that key in protected backups. The list exposes only masked numbers; authorized users load full details through the edit modal with a no-store response. Sensitive values are excluded from model serialization, validation input flashing and audit metadata. Add/view/edit/delete actions are audited without copying bank details into logs. Duplicate accounts per employee are blocked, and version checks prevent an outdated edit or deletion from overriding a newer change.

## Invoices, receivables and payables

Record client invoices as receivables and supplier invoices as payables. Invoices automatically appear in the corresponding ledger, without creating a second record. Receivable/payable entries may also be entered without an invoice number. Required information includes the counterparty, description, issue/due dates and final total in PHP; contact email, notes and a private PDF/image attachment up to 5 MB are optional. Attachments are available only through an authorized download endpoint.

Record actual collections or disbursements with amount, date, method and reference. Partial payments reduce the balance; fully paid records become settled. Outstanding records past their due date are overdue. Row locking, unique submission keys and balance validation prevent replayed submissions and overpayment. Invoice numbers must be unique for the same direction and counterparty. Unpaid records may be voided with a reason and replaced; payment history cannot be voided away. Totals exclude void records and follow the selected filters. These payment records do not initiate transfers or automatically create expense/payroll entries. No tax or invoice-numbering rules are inferred.

This is an operational accounting register, not a general ledger, banking connection or tax filing system.
