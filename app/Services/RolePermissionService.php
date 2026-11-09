<?php

namespace App\Services;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Http\Request;

class RolePermissionService
{
    public const ROLES = [
        'admin' => 'Administrator',
        'manager' => 'Manager',
        'qa_admin' => 'QA Assessment Admin',
        'accounting' => 'Accounting',
        'team_leader' => 'Team Leader',
        'agent' => 'Agent',
        'trainee' => 'Trainee',
        'it_admin' => 'IT Admin',
        'it_support' => 'IT Support',
        'it_developer' => 'IT Developer',
    ];

    public function definitions(): array
    {
        return [
            'attendance.view' => $this->definition('Attendance', 'View attendance records', 'Open attendance registers and view recorded hours.', ['admin', 'accounting', 'team_leader']),
            'attendance.edit' => $this->definition('Attendance', 'Edit attendance', 'Correct punches or enter manual hours. Team leaders remain limited to their teams.', ['admin', 'accounting', 'team_leader']),
            'salaries.view' => $this->definition('Payroll & finance', 'View salaries', 'View payroll, payslip amounts, allowances, and accounting registers.', ['admin', 'accounting']),
            'finance.manage' => $this->definition('Payroll & finance', 'Manage accounting records', 'Create and update expenses, contracts, invoices, receivables, payables, bank information, and backpay.', ['admin', 'accounting']),
            'payroll.manage' => $this->definition('Payroll & finance', 'Manage payroll', 'Create payroll, approve workflow stages, record payments, and maintain government identifiers.', ['admin', 'accounting']),
            'reports.export' => $this->definition('Payroll & finance', 'Export financial reports', 'Download government, tax, payroll, and accounting-ready reports.', ['admin', 'accounting']),
            'trainees.manage' => $this->definition('People', 'Manage trainees', 'Review trainee accounts, assign teams, and update training status.', ['admin', 'qa_admin']),
            'trainees.promote' => $this->definition('People', 'Create employee from trainee', 'Create a new employee account after a trainee passes while retaining the trainee record.', ['admin']),
            'employees.view' => $this->definition('People', 'View employee directory', 'View the administrative employee directory and employment details.', ['admin', 'manager']),
            'employees.manage' => $this->definition('People', 'Manage employees', 'Create and update employee accounts and employment information.', ['admin']),
            'account_status.manage' => $this->definition('People', 'Change account status', 'Change employee or trainee status. Existing team scope rules still apply.', ['admin', 'qa_admin', 'team_leader']),
            'sanctions.manage' => $this->definition('Compliance', 'Issue sanctions', 'Manage the sanctions catalog and issue sanctions to employees.', ['admin']),
            'approvals.manage' => $this->definition('Compliance', 'Final request approvals', 'Approve or reject overtime, undertime, leave, absence, and late requests.', ['admin']),
            'schedules.manage' => $this->definition('Operations', 'Manage campaign schedules', 'Create and update campaign schedules and employee schedule assignments.', ['admin', 'manager']),
            'training.manage' => $this->definition('Training & quality', 'Manage training plans', 'Create training plans, enroll trainees, and manage training configuration.', ['admin', 'qa_admin']),
            'audit.view' => $this->definition('Security', 'View audit trail', 'Review the centralized history of sensitive system changes.', ['admin']),
            'backups.manage' => $this->definition('Security', 'Manage backups & recovery', 'Create, download, restore-test, and monitor encrypted system backups.', ['admin', 'it_admin']),
            'operations.view' => $this->definition('Security', 'View operations health', 'Monitor database, queue, scheduler, storage, backups, failed jobs, and application uptime.', ['admin', 'it_admin']),
            'performance.manage' => $this->definition('People', 'Manage employee performance', 'Create employee goals, KPI reviews, one-on-ones, improvement plans, and appraisal records. Team leaders remain limited to their teams.', ['admin', 'manager', 'team_leader']),
            'leave_credits.manage' => $this->definition('People', 'Manage leave credit ledger', 'Post leave credit expiry, carry-over, payout, and documented manual adjustments.', ['admin', 'accounting']),
            'offboarding.manage' => $this->definition('People', 'Manage employee clearance', 'Create and update property, attendance, deduction, backpay, quitclaim, interview, and deactivation clearance steps.', ['admin', 'accounting']),
            'offboarding.approve' => $this->definition('People', 'Approve final clearance', 'Grant final employee clearance after every required offboarding step is complete.', ['admin']),
            'documents.manage' => $this->definition('People', 'Manage employee document requests', 'Review HR document requests, upload completed files, release documents, and reject requests.', ['admin', 'manager', 'accounting']),
            'compliance_documents.manage' => $this->definition('Compliance', 'Manage document expirations', 'Track expiring contracts, IDs, certifications, medical records, applicant requirements, and training requirements.', ['admin', 'qa_admin']),
            'recruitment.manage' => $this->definition('People', 'Manage recruitment interviews', 'Assign interviewers, schedule available times, send calendar invitations, and track reschedules and no-shows.', ['admin', 'manager', 'qa_admin']),
        ];
    }

    public function can(?User $user, string $permission): bool
    {
        if (! $user || ! $user->canAccessAccount() || ! isset($this->definitions()[$permission])) {
            return false;
        }

        $override = RolePermission::query()->where('role', $user->role)->where('permission', $permission)->value('allowed');

        return $override === null
            ? in_array($user->role, $this->definitions()[$permission]['default_roles'], true)
            : (bool) $override;
    }

    public function permissionsFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if (! $user->canAccessAccount()) {
            return [];
        }
        $overrides = RolePermission::query()->where('role', $user->role)->pluck('allowed', 'permission');

        return collect($this->definitions())->filter(function ($definition, $permission) use ($user, $overrides) {
            return $overrides->has($permission)
                ? (bool) $overrides->get($permission)
                : in_array($user->role, $definition['default_roles'], true);
        })->keys()->values()->all();
    }

    public function matrix(): array
    {
        $overrides = RolePermission::query()->get()->keyBy(fn ($row) => $row->role.'|'.$row->permission);

        return collect(array_keys(self::ROLES))->mapWithKeys(function ($role) use ($overrides) {
            return [$role => collect($this->definitions())->mapWithKeys(function ($definition, $permission) use ($role, $overrides) {
                $override = $overrides->get($role.'|'.$permission);

                return [$permission => $override ? (bool) $override->allowed : in_array($role, $definition['default_roles'], true)];
            })->all()];
        })->all();
    }

    public function allowsRestrictedPath(User $user, Request $request): bool
    {
        $modules = [
            'attendance.view' => ['attendance'],
            'attendance.edit' => ['attendance/*'],
            'salaries.view' => ['accounting', 'accounting/*'],
            'trainees.manage' => ['trainees', 'trainees/*'],
            'employees.view' => ['employees', 'employees/*'],
            'account_status.manage' => ['account-statuses', 'account-statuses/*'],
            'sanctions.manage' => ['sanctions', 'sanctions/*'],
            'schedules.manage' => ['campaign-schedules', 'campaign-schedules/*', 'campaigns', 'campaigns/*', 'teams', 'teams/*'],
            'training.manage' => ['management/training-plans', 'management/training-plans/*'],
            'audit.view' => ['audit-trail'],
            'backups.manage' => ['backup-recovery', 'backup-recovery/*'],
            'operations.view' => ['operations-health'],
            'performance.manage' => ['performance', 'performance/*'],
            'leave_credits.manage' => ['leave-credits/*'],
            'offboarding.manage' => ['offboarding', 'offboarding/*'],
            'documents.manage' => ['document-requests', 'document-requests/*'],
            'compliance_documents.manage' => ['document-expirations', 'document-expirations/*'],
            'recruitment.manage' => ['management/interview-calendar', 'management/interview-calendar/*'],
        ];

        foreach ($modules as $permission => $patterns) {
            if ($request->is(...$patterns) && $this->can($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    private function definition(string $group, string $label, string $description, array $defaultRoles): array
    {
        return ['group' => $group, 'label' => $label, 'description' => $description, 'default_roles' => $defaultRoles];
    }
}
