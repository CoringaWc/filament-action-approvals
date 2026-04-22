<?php

use CoringaWc\FilamentActionApprovals\ApproverResolvers\CustomRuleResolver;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\RoleResolver;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */
    'user_model' => config('auth.providers.users.model'),

    /*
    |--------------------------------------------------------------------------
    | Approver Resolvers
    |--------------------------------------------------------------------------
    | Registered resolver classes available in the flow builder UI.
    */
    'approver_resolvers' => [
        UserResolver::class,
        RoleResolver::class,
        CustomRuleResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    | Enable multi-tenancy to scope approval flows and approvers per tenant.
    | When enabled, the tenant_column is used on the approval_flows table
    | and on models/users to isolate approvals per tenant.
    |
    | Set column to match your application's tenant foreign key
    | (e.g. 'company_id', 'team_id', 'organization_id').
    |
    | scope_approvers: When true, role-based resolvers will also filter
    | users by the tenant column.
    */
    'multi_tenancy' => [
        'enabled' => false,
        'column' => 'company_id',
        'scope_approvers' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA Warning Threshold
    |--------------------------------------------------------------------------
    | Fraction of SLA time elapsed before sending a warning (0.75 = 75%).
    */
    'sla_warning_threshold' => 0.75,

    /*
    |--------------------------------------------------------------------------
    | Date Display
    |--------------------------------------------------------------------------
    | Controls how dates are rendered in the package UI.
    |
    | display_format: PHP date format used for absolute dates and tooltips.
    | use_since: when true, date fields use relative time (diffForHumans).
    */
    'date' => [
        'display_format' => 'd/m/Y H:i',
        'use_since' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-register SLA Command Schedule
    |--------------------------------------------------------------------------
    | When true, the package registers `approval:process-sla` to run every minute.
    */
    'schedule_sla_command' => true,

    /*
    |--------------------------------------------------------------------------
    | Navigation Group
    |--------------------------------------------------------------------------
    */
    'navigation_group' => null,

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    | Toggle each operational approval action independently.
    | Disable actions you do not want exposed in the built-in ApprovalResource,
    | contextual action flows, or reusable package UI.
    */
    'actions' => [
        // Show the approve action for eligible users.
        'approve' => true,

        // Show the reject action for eligible users.
        'reject' => true,

        // Show the comment action in built-in operational UIs.
        'comment' => false,

        // Show the delegate action in built-in operational UIs.
        'delegate' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    | Controls how approval notifications are delivered to users.
    |
    | database: Store notifications in the database (notifications table).
    |           Default true — matches the behaviour before this config key existed.
    |
    | broadcast: After saving to the database, dispatch Filament's
    |            DatabaseNotificationsSent event so the notification bell
    |            updates in real-time via WebSocket (Reverb/Pusher).
    |            Requires database driver and a running broadcast server.
    |            Default false — opt-in to avoid extra WebSocket traffic.
    |
    | This controls ALL package notification classes:
    |   ApprovalRequestedNotification, ApprovalApprovedNotification,
    |   ApprovalRejectedNotification, ApprovalCancelledNotification,
    |   ApprovalSlaWarningNotification, ApprovalEscalatedNotification.
    */
    'notifications' => [
        'database' => true,
        'broadcast' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    | Enable broadcasting per event. When enabled, the event implements
    | ShouldBroadcast and is pushed to the broadcasting queue.
    | All events are disabled by default — opt-in per event.
    */
    'broadcasting' => [
        'events' => [
            'submitted' => false,
            'approved' => false,
            'rejected' => false,
            'cancelled' => false,
            'commented' => false,
            'delegated' => false,
            'step_completed' => false,
            'escalated' => false,
        ],
        'queue' => null, // null = default queue
    ],

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    | When enabled, users matching the configured roles or IDs can see and
    | act on all approval actions regardless of being an assigned approver.
    |
    | Works with or without spatie/laravel-permission.
    */
    'super_admin' => [
        'enabled' => false,
        'roles' => ['super_admin'],
        'user_ids' => [],
        'hide_from_selects' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Customization
    |--------------------------------------------------------------------------
    | Customize the built-in ApprovalFlowResource appearance and behavior.
    | Set 'enabled' to false to hide the resource entirely.
    | To fully customize, extend ApprovalFlowResource in your app.
    */
    'resource' => [
        'enabled' => true,
        'cluster' => null,
        'navigation_sort' => null,
        'navigation_icon' => null,
        'show_widgets' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Approval Resource Customization
    |--------------------------------------------------------------------------
    | Customize the built-in ApprovalResource appearance and behavior.
    | Set 'enabled' to false to hide the resource entirely.
    */
    'approvals_resource' => [
        'enabled' => true,
        'navigation_sort' => null,
        'navigation_icon' => null,
        'group_record_actions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Customization
    |--------------------------------------------------------------------------
    | Enable the built-in approvals dashboard when you want a global operational
    | view with widgets and period filters. It stays disabled by default so the
    | consuming panel can opt in explicitly.
    */
    'dashboard' => [
        'enabled' => false,

        // Route path used by the custom dashboard page inside the Filament panel.
        'route_path' => 'approvals-dashboard',

        // Optional navigation sort override for the dashboard page.
        'navigation_sort' => null,

        // Optional navigation icon override for the dashboard page.
        'navigation_icon' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel Dashboard Widgets
    |--------------------------------------------------------------------------
    | These widgets are registered on the panel's primary dashboard via
    | Panel::widgets(). When null, the package keeps them enabled only when the
    | dedicated ApprovalsDashboard page is disabled, avoiding a merged dashboard
    | experience by default.
    */
    'widgets' => [
        'enabled' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table Prefix
    |--------------------------------------------------------------------------
    | Prefix for all package tables.
    */
    'table_prefix' => '',

];
