<?php

return [

    // General
    'approval' => 'Approval',
    'approvals' => 'Approvals',

    // Navigation
    'navigation_group' => 'Approvals',
    'flow_resource_label' => 'Approval Flow',
    'flow_resource_plural' => 'Approval Flows',

    // Statuses
    'status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ],

    // Step types
    'step_type' => [
        'single' => 'Single Approver',
        'sequential' => 'Sequential',
        'parallel' => 'Parallel',
    ],

    // Action types
    'action_type' => [
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'commented' => 'Commented',
        'delegated' => 'Delegated',
        'escalated' => 'Escalated',
        'returned' => 'Returned',
    ],

    // Step instance statuses
    'step_status' => [
        'pending' => 'Pending',
        'waiting' => 'Waiting',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'skipped' => 'Skipped',
    ],

    // Escalation actions
    'escalation' => [
        'notify' => 'Send Reminder',
        'auto_approve' => 'Auto-Approve',
        'reassign' => 'Reassign',
        'reject' => 'Auto-Reject',
    ],

    // Resolver labels
    'resolvers' => [
        'user' => 'Specific Users',
        'role' => 'Users by Role',
        'custom_rule' => 'Custom Rule',
    ],

    // Flow resource form
    'flow' => [
        'flow_details' => 'Flow Details',
        'name' => 'Name',
        'description' => 'Description',
        'applies_to' => 'Applies To',
        'any_model' => 'Any Model',
        'applies_to_helper' => 'Leave blank to apply to any model',
        'is_active' => 'Active',
        'approval_steps' => 'Approval Steps',
        'step_name' => 'Step Name',
        'type' => 'Type',
        'approver_type' => 'Approver Type',
        'required_approvals' => 'Required Approvals',
        'required_approvals_hint' => 'Require :required of :total selected approvers',
        'required_approvals_helper' => 'How many approvers must approve for this step to pass',
        'sla_hours' => 'Response deadline (hours)',
        'sla_helper' => 'Leave blank to keep this step without an automatic deadline',
        'escalation_action' => 'What to do when the deadline expires',
        'add_step' => 'Add Step',
        'action_key' => 'Action',
        'any_action' => 'Any action',
        'action_key_helper' => 'Optional. When filled, the flow only applies to that model action. When left empty, the flow may be used for any action of that model.',
        'select_model_first' => 'Select a model to list the available actions.',
    ],

    // Flow resource table
    'flow_table' => [
        'name' => 'Name',
        'model' => 'Model',
        'any' => 'Any',
        'steps' => 'Steps',
        'is_active' => 'Active',
        'created_at' => 'Created At',
        'action_key' => 'Action',
    ],

    // Common field labels
    'fields' => [
        'status' => 'Status',
        'type' => 'Type',
        'comment' => 'Comment',
        'submitted_at' => 'Submitted At',
        'completed_at' => 'Completed At',
    ],

    // Actions
    'actions' => [
        'submit' => 'Submit for Approval',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'comment' => 'Comment',
        'delegate' => 'Delegate',

        'approval_flow' => 'Approval Flow',
        'approval_action' => 'Action to approve',
        'approval_action_helper' => 'Choose which business scenario is being submitted. The system will try a flow specific to that action and fall back to the model generic flow when none exists.',
        'comment_optional' => 'Comment (optional)',
        'rejection_reason' => 'Reason for rejection',
        'delegate_to' => 'Delegate to',
        'reason' => 'Reason',

        'approve_heading' => 'Approve this record?',
        'reject_heading' => 'Reject this record?',

        // Success messages
        'submitted_success' => 'Submitted for approval',
        'approved_success' => 'Approved',
        'rejected_success' => 'Rejected',
        'comment_success' => 'Comment added',
        'delegated_success' => 'Delegated successfully',
    ],

    // Notifications
    'notifications' => [
        'requested_title' => 'Approval Requested: :step',
        'requested_body' => ':model #:id requires your approval.',
        'approved_title' => 'Approval Completed',
        'approved_body' => ':model #:id has been approved.',
        'rejected_title' => 'Approval Rejected',
        'rejected_body' => ':model #:id has been rejected.',
        'escalated_title' => 'Approval deadline expired',
        'escalated_body' => ':model #:id has passed its configured deadline.',
        'sla_warning_title' => 'Approval deadline is approaching',
        'sla_warning_body' => ':model #:id must be approved by :deadline.',
    ],

    // Widgets
    'widgets' => [
        'pending_heading' => 'My Pending Approvals',
        'step' => 'Step',
        'record' => 'Record',
        'since' => 'Since',
        'due' => 'Due',
        'no_sla' => 'No deadline set',
        'pending_approvals' => 'Pending Approvals',
        'approved_30d' => 'Approved (30d)',
        'rejected_30d' => 'Rejected (30d)',
        'overdue_steps' => 'Steps past deadline',
    ],

    // Relation manager
    'relation_manager' => [
        'title' => 'Approvals',
        'flow' => 'Flow',
        'submitted_by' => 'Submitted By',
        'in_progress' => 'In Progress',
        'approval_details' => 'Approval Details',
        'steps' => 'Steps',
        'audit_trail' => 'Audit Trail',
        'approvers' => 'Approvers',
        'received_required' => 'Received / Required',
        'by' => 'By',
        'system' => 'System',
        'date' => 'Date',
        'close' => 'Close',
        'approval_heading' => 'Approval: :flow',
        'not_available' => 'N/A',
    ],

    // Infolist section
    'infolist' => [
        'approval_status' => 'Approval Status',
        'status' => 'Status',
        'flow' => 'Flow',
        'submitted_by' => 'Submitted By',
        'submitted' => 'Submitted',
        'completed' => 'Completed',
        'not_submitted' => 'Not Submitted',
        'in_progress' => 'In Progress',
        'current_step' => 'Current Step',
        'step' => 'Step',
        'pending_approvers' => 'Pending Approvers',
        'progress' => 'Progress',
        'approvals_count' => ':received / :required approvals',
        'sla_deadline' => 'Deadline',
        'no_sla' => 'No deadline set',
        'recent_activity' => 'Recent Activity',
        'by' => 'By',
        'system' => 'System',
        'date' => 'Date',
        'no_approval' => 'No Approval',
        'not_available' => 'N/A',
        'rejection_reason' => 'Rejection Reason',
    ],

    // Status column
    'column' => [
        'label' => 'Approval',
        'no_approval' => 'No Approval',
    ],

    // Resolver config
    'resolver_config' => [
        'users' => 'Users',
        'role' => 'Roles',
        'custom_rule' => 'Custom Rule',
    ],

    'flow_hints' => [
        'name' => 'Internal flow name. Use a clear title so this process is easy to identify in lists and audit history.',
        'description' => 'Optional description to explain when this flow should be used.',
        'applies_to' => 'Defines which record type can use this flow. Leave blank to allow any compatible model.',
        'action_key' => 'Use this field to restrict the flow to a specific model action when you have multiple approval scenarios.',
        'is_active' => 'Disable it to stop new submissions without losing the existing configuration.',
        'steps' => 'Register the steps in the order the approval should happen. Each item defines who approves, in which format, and with which deadline.',
        'step_name' => 'Name shown to users and in the audit history for this step.',
        'type' => 'Choose whether the step uses a single approver, a sequential chain, or parallel approvals.',
        'approver_type' => 'Defines the rule used to resolve approvers for this step.',
        'required_approvals' => 'For parallel steps, this is how many approvals are required to complete the step.',
        'sla_hours' => 'Maximum response time for the step, in hours. Leave empty to disable SLA.',
        'escalation_action' => 'Automatic action executed when the deadline for this step expires.',
        'resolver_users' => 'Select the specific users who may approve this step.',
        'resolver_role' => 'All users with any of the selected roles will be considered approvers for this step.',
        'resolver_custom_rule' => 'Choose the custom rule that dynamically resolves approvers at runtime.',
    ],

    'select' => [
        'search_prompt' => 'Type to search',
        'no_options' => 'No options available',
        'no_search_results' => 'No results found',
        'loading' => 'Loading options...',
    ],

    // Tabs (List page)
    'tabs' => [
        'all' => 'All',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'My Pending',
    ],

    // SLA command
    'sla' => [
        'auto_approved' => 'Auto-approved because the deadline expired',
        'auto_rejected' => 'Auto-rejected because the deadline expired',
    ],

];
