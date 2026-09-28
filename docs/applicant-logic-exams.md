# Applicant logic exams

Admins and QA Assessment Admins use **Training & Development → Applicant Logic Exam**. Other roles cannot manage questions or read results, including by calling the endpoints directly.

1. Add questions manually or generate AI drafts (up to five per request).
2. Review each question, its four choices, correct answer and explanation. Check the approval box only after verifying the question. AI drafts are never automatically approved.
3. Set the exam title, instructions, number of questions, duration and passing mark. Enable the exam once enough approved questions exist.
4. Applicants complete a randomized exam in the existing careers application form. All scores are submitted for human review; exam scores do not automatically change application status.
5. Open **Applicant results → View answers** for the applicant's score and answer breakdown in a modal.

The feature is disabled initially, so existing applications remain available while the question bank is being reviewed. Previous applications do not receive retroactive exams.

## AI configuration

Set `OPENAI_API_KEY` privately in the server `.env`. Optional `OPENAI_EXAM_MODEL` defaults to `gpt-4.1-mini`. Refresh Laravel's configuration cache after configuring the key. Never put the key in frontend variables, source control, or the SFTP upload list.

Generation uses OpenAI's Responses API with a strict JSON schema and `store: false`. Only the supplied topics, difficulty and question count are sent; applicant identities, resumes and results are not sent. Requests have a 25-second timeout and a limit of two requests per minute per authenticated user. Invalid output, refusals and incomplete responses save no questions. Generation is unavailable without a key; manual authoring works independently. Review remains necessary because valid JSON does not guarantee a correct question or answer.

References: [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs), [GPT-4.1 mini](https://developers.openai.com/api/docs/models/gpt-4.1-mini).

## Submission protections

Attempts are bound to the applicant's browser session. Question and option order are randomized, and answer keys and explanations are excluded from public responses. The server enforces the expiry, exact set of answers and single submission. Application and result are saved in one transaction. A question snapshot keeps prior attempts stable if the bank is edited later. Scores support recruitment review and are not an identity verification or proctoring mechanism.

The timer includes the final application submission, so applicants should complete their personal details first. An expired attempt can be restarted; the page preserves application fields. Changing browsers or losing the session requires a new attempt.

## Validation

`php artisan test --compact tests/Feature/ApplicantExamTest.php tests/Feature/JobApplicationWorkflowTest.php tests/Feature/QaAdminPortalTest.php`

Provider calls are faked in automated tests. An actual AI generation check requires a configured API key and provider access.
