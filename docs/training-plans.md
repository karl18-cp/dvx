# Training plans and allowances

Admins and QA assessment admins manage plans under **Training & Development → Training Plans**. Plans belong to a campaign and specify a start date, training weekdays, phase durations, PHP daily rates, and the number of attended days required for the first allowance. The phase order is oral training, call training, then nesting. A zero-day phase is skipped.

Enroll active trainees from the plan's campaign. A trainee can have one plan; existing enrollments cannot be removed from a started plan. Trainees view their own plan and allowance breakdown under **Training Plan & Allowance**. Graduated agents retain access under **Training Allowance**. Admin and QA users can inspect each enrolled trainee through an allowance modal.

Only completed time-in and time-out with positive worked minutes earns a daily allowance. Calculations use the existing attendance service, including schedule snapshots, approved requests, and break deductions. The daily allowance is not prorated for partial days. Missing or incomplete attendance, future days, days outside the training calendar, and dates after graduation/rejection do not accrue allowance. Existing campaign schedules still govern attendance clock times; a training plan does not replace those schedules.

The first allowance is the sum of rates on the first N qualifying attended dates. Absences delay eligibility, and crossing into another phase can change the amount compared with the full-attendance projection. The total accrued allowance continues beyond the first threshold. Eligibility is not a payment record and does not transfer funds. The administrator handles actual release.

Rates are stored and summed as integer centavos. Changing a plan or correcting attendance recalculates estimates. Plan changes and enrollment selections are recorded in the existing activity log. The allowance screen is an accrual estimate, not an immutable payroll ledger.

Tests: `php artisan test --compact --filter='TrainingPlanTest|TraineePortalTest|QaAdminPortalTest'`.
