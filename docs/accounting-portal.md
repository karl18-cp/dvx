# Accounting portal

Admins can open **Accounting** from the sidebar. Set an employee's position to **Accounting** in employee management to grant the dedicated portal. Accounting accounts land here after login and can also access their own settings and notifications; other operational modules are blocked server-side. Agents, team leaders, trainees, managers and QA admins cannot access accounting data.

## Payroll

Create a draft for an employee, pay period, reviewed gross earnings and deductions in PHP. Explain deductions in the notes. Net pay uses integer centavos, and overlapping non-void payroll periods for the same employee are rejected. Salary, tax and overtime formulas are not inferred from attendance: earnings and deductions must be calculated and reviewed before entry.

Review the draft, approve it, then record an actual payment's date, method and reference. Recording a payment does not transfer money. Unpaid drafts or approvals can be voided with a reason and replaced; recorded payments are read-only. Approval is a separate action but does not require a different staff member.

## Training allowances

Choose an enrolled trainee and attendance cutoff. The existing training plan determines attended days, phase rates and first payout threshold. The first draft includes the first configured number of attended days. Once it is recorded as paid, subsequent drafts include remaining eligible unclaimed days up to the selected cutoff.

Drafts reserve attendance dates; a unique database constraint prevents duplicate claims. Approval and payment recheck eligibility and rates. If the source changed, void the unpaid entry and prepare it again. Paid entries retain their original amount and source snapshot.

The register separates draft, approved and paid totals. Audit logs retain actors and entry snapshots. Account deletion is blocked when financial records refer to that account.

Recording payment creates an in-app notification for the recipient and enters the existing employee email-notification workflow. Recipients do not receive access to other employees' accounting records.

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
