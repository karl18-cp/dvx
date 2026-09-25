# QA Assessment Admin

The `qa_admin` role uses the normal employee login and opens the QA Dashboard after sign-in.

An administrator can choose **QA Assessment Admin** when adding an employee or changing an existing employee's role in Employees. Existing accounts are not automatically reassigned.

The sidebar contains Training & Development (assessments, assignments, training library, coaching, reviews and analytics) and Quality Assurance (QA dashboard, evaluations and scorecards). Account settings and logout remain available. Messaging and unrelated employee/admin navigation are excluded.

The role can manage training and QA across campaigns. `RestrictQaAdminAccess` also denies unrelated routes, including applicant management, employee administration, attendance, requests, forms and sanctions. The restriction applies to both normal and opaque URLs. Existing admin, manager and team leader permissions remain in place.

No schema migration is required: the existing users.role string stores the new role.
