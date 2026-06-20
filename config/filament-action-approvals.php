<?php

use CoringaWc\FilamentActionApprovals\ApproverResolvers\CustomRuleResolver;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\RoleResolver;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Models\ApprovalAction;
use CoringaWc\FilamentActionApprovals\Models\ApprovalDelegation;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStep;
use CoringaWc\FilamentActionApprovals\Models\ApprovalStepInstance;

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */
    'user_model' => config('auth.providers.users.model'),

    /*
    |--------------------------------------------------------------------------
    | Package Models
    |--------------------------------------------------------------------------
    | Override these classes when your application needs project-owned models
    | with additional casts, relationships, scopes, observers, or policies.
    | Each override must extend the corresponding package model below.
    */
    'models' => [
        'approval' => Approval::class,
        'approval_flow' => ApprovalFlow::class,
        'approval_step' => ApprovalStep::class,
        'approval_step_instance' => ApprovalStepInstance::class,
        'approval_action' => ApprovalAction::class,
        'approval_delegation' => ApprovalDelegation::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | User Key
    |--------------------------------------------------------------------------
    | Used by package migrations for approval actors. Leave null to infer from
    | the configured user model, or set explicitly to integer, uuid, ulid, or
    | string when your application uses a custom primary-key strategy.
    */
    'user_table' => null,
    'user_key_type' => null,
    'user_key_length' => 255,

    /*
    |--------------------------------------------------------------------------
    | Approver Resolvers
    |--------------------------------------------------------------------------
    | Registered resolver classes available in the flow builder UI. Resolvers
    | can hide themselves with isAvailable(); RoleResolver is hidden when
    | spatie/laravel-permission is not installed or not used by the user model.
    */
    'approver_resolvers' => [
        UserResolver::class,
        RoleResolver::class,
        CustomRuleResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    | limit_to_current_panel: When true, the RoleResolver select only lists
    | roles that belong to the current Filament panel, when the roles table
    | supports a "panel" column.
    */
    'roles' => [
        'limit_to_current_panel' => true,
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
    | Pending Submissions
    |--------------------------------------------------------------------------
    | block_concurrent prevents a second pending approval for the same
    | approvable record and action key. Disable it when your domain explicitly
    | allows multiple simultaneous requests for the same operation.
    */
    'pending_submissions' => [
        'block_concurrent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Native Operation Interception
    |--------------------------------------------------------------------------
    | When enabled on a panel, the plugin configures Filament EditAction and
    | DeleteAction. Models that use HasApprovals and declare approvable
    | operations submit configured operations for approval instead of mutating
    | directly when a matching approval flow exists.
    */
    'operations' => [
        'intercept' => false,
        'modal_callout' => true,
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
        'channel' => 'approval-events',
        'private' => true,
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
    | Privileged Users
    |--------------------------------------------------------------------------
    | Privileged users are trusted actors who can operate above the regular
    | approval flow. The legacy `super_admin` block below is kept as a
    | deprecated alias and is merged into this block at resolution time.
    |
    | Works with or without spatie/laravel-permission.
    */
    'privileged' => [
        // Master switch for every privileged capability below. When false,
        // privileged checks return false even if roles or IDs are configured.
        'enabled' => false,

        // Role names whose users are treated as privileged. Requires
        // spatie/laravel-permission (resolved via the user's hasAnyRole()).
        'roles' => ['super_admin'],

        // Explicit user IDs treated as privileged regardless of their roles.
        'user_ids' => [],

        // Hide privileged users/roles from approver resolver selects so they
        // are not picked as regular approvers.
        'hide_from_selects' => true,

        // Bypass capabilities granted to privileged users.
        'bypass' => [
            // Allow privileged users to apply an approvable action directly,
            // without creating an approval record. Applications should execute
            // their normal save path when this returns true.
            'apply_directly' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Super Admin (Deprecated)
    |--------------------------------------------------------------------------
    | Deprecated alias of the `privileged` block above. Existing keys keep
    | working and are merged into `privileged` (enabled via OR, roles and
    | user_ids via union, hide_from_selects via AND). Prefer `privileged`.
    |
    | When enabled, users matching the configured roles or IDs can see and
    | act on all approval actions regardless of being an assigned approver.
    */
    'super_admin' => [
        'enabled' => false,
        'roles' => [],
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
