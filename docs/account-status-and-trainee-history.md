# Account status and retained trainee accounts

The Employee Status tab is available to active admins, QA assessment admins, and team leaders. Admins manage other accounts; QA admins manage agents, team leaders, and trainees; team leaders manage only agents currently in their assigned teams. Self-status changes are blocked. Every change records the actor, old/new statuses, and notes in the existing activity log.

Employees can sign in only when Active. Floating, Resigned, Suspended, and Terminated accounts are blocked. Trainees can sign in when Active Trainee or Passed, and are blocked when Failed. Existing internal values remain `in_training`, `graduated`, and `rejected` for compatibility. The access middleware refreshes the user on each request, and disabled accounts lose sessions and remember tokens. The login event also checks status for authentication flows such as passkeys.

Marking Passed now retains the trainee's role, DVXTR ID, password, training plan, attendance, coaching, and other history. The passed trainee can review records, but cannot add new attendance punches. An admin can separately use **Trainees → Create employee account** once. It allocates a new DVX ID and copies personal information, face enrollment, assigned schedule, and a physically separate copy of the profile photo. The new account is an Agent with a temporary password equal to the new DVX ID. Its attendance and other employment records start separately. Passkeys, two-factor secrets, passwords, and sessions are not copied. Production team assignment remains an admin/team-leader action.

The employee's `source_trainee_id` links the two records. Linked accounts cannot use self-service profile deletion, preserving the source record. Older accounts already converted to Agent are not retroactively split or renumbered.

Linked accounts can share their email address. Ordinary onboarding still rejects duplicate emails, and profile/employee edits permit sharing only within the linked pair. Sign-in and password recovery require the DVX or DVXTR ID when an email belongs to multiple accounts. Password reset tokens are keyed by account ID, preventing a token for one account from resetting the other. Reset links created before this change need to be requested again.

Verification covers status scope, disabled sessions, retained trainee identities, duplicate employee prevention, photo/face and personal-information copies, and cross-account password reset rejection. Existing authentication, onboarding, profile, trainee, attendance, QA, and team-access tests also run.
