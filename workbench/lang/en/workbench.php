<?php

return [
    'resources' => [
        'purchase_orders' => [
            'model_label' => 'Purchase Order',
            'plural_model_label' => 'Purchase Orders',
            'navigation_label' => 'Purchase Orders',
            'navigation_group' => 'Procurement',
            'fields' => [
                'requester' => 'Requester',
                'title' => 'Title',
                'description' => 'Description',
                'amount' => 'Amount',
            ],
            'columns' => [
                'title' => 'Title',
                'requester' => 'Requester',
                'amount' => 'Amount',
                'created_at' => 'Created At',
            ],
        ],
        'expenses' => [
            'model_label' => 'Expense',
            'plural_model_label' => 'Expenses',
            'navigation_label' => 'Expenses',
            'navigation_group' => 'Finance',
            'fields' => [
                'requester' => 'Requester',
                'title' => 'Title',
                'description' => 'Description',
                'category' => 'Category',
                'amount' => 'Amount',
            ],
            'columns' => [
                'title' => 'Title',
                'requester' => 'Requester',
                'category' => 'Category',
                'amount' => 'Amount',
                'status' => 'Status',
                'created_at' => 'Created At',
            ],
            'categories' => [
                'travel' => 'Travel',
                'supplies' => 'Supplies',
                'equipment' => 'Equipment',
                'training' => 'Training',
            ],
            'statuses' => [
                'draft' => 'Draft',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
            ],
        ],
        'invoices' => [
            'model_label' => 'Invoice',
            'plural_model_label' => 'Invoices',
            'navigation_label' => 'Invoices',
            'navigation_group' => 'Finance',
            'sections' => [
                'overview' => 'Overview',
            ],
            'fields' => [
                'requester' => 'Requester',
                'number' => 'Number',
                'title' => 'Title',
                'description' => 'Description',
                'amount' => 'Amount',
                'status' => 'Status',
                'previous_status' => 'Previous Status',
                'sent_at' => 'Sent At',
                'paid_at' => 'Paid At',
                'cancelled_at' => 'Cancelled At',
                'created_at' => 'Created At',
            ],
            'columns' => [
                'number' => 'Number',
                'title' => 'Title',
                'requester' => 'Requester',
                'amount' => 'Amount',
                'status' => 'Status',
                'created_at' => 'Created At',
            ],
            'states' => [
                'issuing' => 'Issuing',
                'sent' => 'Sent',
                'awaiting_payment' => 'Awaiting Payment',
                'paid' => 'Paid',
                'cancelled' => 'Cancelled',
            ],
            'actions' => [
                'advance_to' => 'Advance to :status',
                'advance_default' => 'Advance status',
                'advance_modal_heading' => 'Advance to :status?',
                'advance_modal_description' => 'This action updates the invoice state machine and may require approval.',
                'cancel' => 'Cancel invoice',
                'cancel_modal_heading' => 'Cancel this invoice?',
                'cancel_modal_description' => 'This action may require approval and should not be used on finalized invoices.',
            ],
            'notifications' => [
                'transition_pending_title' => 'Transition submitted for approval',
                'transition_pending_body' => 'The status change will remain pending until the configured flow is approved.',
                'transition_success_title' => 'Status updated',
                'transition_success_body' => 'The transition was executed immediately.',
                'cancel_pending_title' => 'Cancellation submitted for approval',
                'cancel_pending_body' => 'The cancellation will wait for approval before being executed.',
                'cancel_success_title' => 'Invoice cancelled',
                'cancel_success_body' => 'The invoice was cancelled immediately.',
            ],
        ],
        'users' => [
            'model_label' => 'User',
            'plural_model_label' => 'Users',
            'navigation_label' => 'Users',
            'navigation_group' => 'Access Control',
            'fields' => [
                'name' => 'Name',
                'email' => 'Email',
                'password' => 'Password',
                'roles' => 'Roles',
            ],
            'columns' => [
                'name' => 'Name',
                'email' => 'Email',
                'roles' => 'Roles',
            ],
        ],
        'roles' => [
            'model_label' => 'Role',
            'plural_model_label' => 'Roles',
            'navigation_label' => 'Roles & Permissions',
            'navigation_group' => 'Access Control',
        ],
    ],
    'roles' => [
        'super_admin' => 'Super Admin',
        'manager' => 'Manager',
        'director' => 'Director',
        'requester' => 'Requester',
    ],
    'approval_actions' => [
        'purchase_orders' => [
            'submit' => 'Submit purchase order',
            'cancel' => 'Cancel purchase order',
        ],
        'expenses' => [
            'submit' => 'Submit expense',
            'reimburse' => 'Reimburse expense',
        ],
    ],
    'seeds' => [
        'users' => [
            'admin' => ['name' => 'Admin User'],
            'manager' => ['name' => 'Manager User'],
            'director' => ['name' => 'Director User'],
            'requester' => ['name' => 'Requester User'],
        ],
        'flows' => [
            'purchase_order' => [
                'name' => 'Purchase Order Approval',
                'description' => 'Two-step approval for purchase orders: manager then director.',
                'manager_step' => 'Manager Approval',
                'director_step' => 'Director Approval',
            ],
            'invoice_send' => [
                'name' => 'Invoice send approval',
                'description' => 'Single-step flow to approve sending an invoice.',
                'manager_step' => 'Manager Approval',
            ],
            'expense_submit' => [
                'name' => 'Expense submission approval',
                'description' => 'Single-step flow to approve sending an expense for review.',
                'manager_step' => 'Manager Approval',
            ],
            'expense_reimburse' => [
                'name' => 'Expense reimbursement approval',
                'description' => 'Single-step flow to approve reimbursing an expense.',
                'director_step' => 'Director Approval',
            ],
        ],
        'invoices' => [
            'issuing' => [
                'title' => 'Invoice waiting to be sent',
            ],
            'sent' => [
                'title' => 'Invoice sent to the vendor',
            ],
            'awaiting_payment' => [
                'title' => 'Invoice awaiting payment',
            ],
        ],
    ],
];
