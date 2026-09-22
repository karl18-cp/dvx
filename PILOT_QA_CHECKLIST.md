# DVX Assessment Pilot Browser Sign-off

QA URL: `http://127.0.0.1:8000`  
Date: __________  Tester: __________  Build/version: __________

Use test accounts and test assignments only. Record Pass/Fail and evidence for every item.

## Employee workflow

- [ ] Test employee can log in and sees only their assigned assessment with correct category, due date, passing score, attempts, and status.
- [ ] Start is blocked before required training; a direct start request is also rejected.
- [ ] Opening and completing required training changes the assessment to Ready.
- [ ] Start creates Attempt 1 once; a rapid second start resumes it instead of creating a duplicate.
- [ ] Record question/option order. Refresh and log out/in; the order remains identical.
- [ ] Save multiple choice, true/false, multiple selection, and short answer responses. UI shows Saving, then Saved only after success.
- [ ] Disconnect briefly during autosave. UI shows Save Failed and resumes saving after reconnection.
- [ ] Refresh: saved answers remain and the timer does not reset.
- [ ] Expired attempt rejects answer changes and follows the submission path.
- [ ] Submit twice rapidly. One submission, answer set, attempt, and skill-result set exists.
- [ ] Submission containing short answers shows Pending Review, never Passed/Failed.

## Admin/manager grading

- [ ] Test admin and manager can open Pending Reviews; an employee receives 403.
- [ ] Grade one response and save: attempt remains Pending Review.
- [ ] Negative and above-maximum points are rejected by the server.
- [ ] Grade all responses with feedback and finalize.
- [ ] Existing attempt is updated; no new attempt is created.
- [ ] Objective/manual totals, percentage, skills, pass/fail, assignment state, grader metadata, and audit log are correct.

## History, progress, and retake

- [ ] Employee History shows the finalized attempt, score, feedback, skill results, time, and attempt number.
- [ ] Retake creates Attempt 2 without changing Attempt 1; Attempt 3 is blocked when maximum attempts is two.
- [ ] History shows both attempts separately.
- [ ] My Progress uses the best finalized attempt and excludes Pending Review from averages.
- [ ] Edit a live question after submission; historical wording and scoring remain unchanged.

## Analytics and security

- [ ] Results filters work individually and in combination; pagination preserves filters.
- [ ] Assessment, team, objective-question, option-distribution, and short-answer statistics match the test data.
- [ ] Employee cannot access another employee’s assignment, training, media, attempt, autosave, result, history, or progress by changing IDs.
- [ ] Employee cannot open management, grading, results, or team analytics URLs.
- [ ] For an assessment with answer review disabled, inspect Inertia/network payloads: no correct IDs, correctness flags, options, answer key, or feedback is present.

## Sign-off

Critical defects: ____________________________________________________________

Non-critical limitations: ___________________________________________________

Result: [ ] PASS  [ ] FAIL

Tester signature: ____________________  Date/time: ____________________
