export const accountingSections = [
    {
        slug: 'payment-register',
        title: 'Payment register',
        description: 'Review payroll drafts, approvals, and recorded payments.',
    },
    {
        slug: 'training-allowances',
        title: 'Training allowances',
        description:
            'Prepare trainee allowances from eligible attendance days.',
    },
    {
        slug: 'expenses',
        title: 'Expenses',
        description:
            'Record business expenses and review spending by category.',
    },
    {
        slug: 'bank-information',
        title: 'Bank information',
        description: 'Manage employee bank details for payees and payors.',
    },
    {
        slug: 'contracts',
        title: 'Employee contracts',
        description:
            'Store training and employment contracts linked to employee accounts.',
    },
    {
        slug: 'invoices',
        title: 'Invoices',
        description:
            'Keep client and supplier invoices with their payment history.',
    },
    {
        slug: 'receivables',
        title: 'Receivables',
        description: 'Track client balances and collections received.',
    },
    {
        slug: 'payables',
        title: 'Payables',
        description: 'Track supplier balances and payments made.',
    },
    {
        slug: 'backpay',
        title: 'Backpay',
        description:
            'Prepare and track final pay for resigned and terminated employees.',
    },
    {
        slug: '13th-month-pay',
        title: '13th month pay',
        description:
            'Review the running accrual calculated from paid basic payroll earnings.',
    },
] as const;

export const accountingSectionUrl = (slug: string) =>
    `/accounting/workspace/${slug}`;
