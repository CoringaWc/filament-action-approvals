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
        ],
    ],
];
