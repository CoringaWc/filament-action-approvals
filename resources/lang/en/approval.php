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
        'callback' => 'Custom Callback',
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
        'sla_hours' => 'SLA (hours)',
        'sla_helper' => 'Leave blank for no SLA',
        'escalation_action' => 'Escalation Action',
        'add_step' => 'Add Step',
        'action_key' => 'Action',
        'action_key_helper' => 'Select the specific action that requires approval. Available after selecting the model.',
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
        'escalated_title' => 'Approval Escalated',
        'escalated_body' => ':model #:id has breached its SLA deadline.',
        'sla_warning_title' => 'SLA Warning: Approval Due Soon',
        'sla_warning_body' => ':model #:id approval is due :deadline.',
    ],

    // Widgets
    'widgets' => [
        'pending_heading' => 'My Pending Approvals',
        'step' => 'Step',
        'record' => 'Record',
        'since' => 'Since',
        'due' => 'Due',
        'no_sla' => 'No SLA',
        'pending_approvals' => 'Pending Approvals',
        'approved_30d' => 'Approved (30d)',
        'rejected_30d' => 'Rejected (30d)',
        'overdue_steps' => 'Overdue Steps',
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
        'sla_deadline' => 'SLA Deadline',
        'no_sla' => 'No SLA',
        'recent_activity' => 'Recent Activity',
        'by' => 'By',
        'system' => 'System',
        'date' => 'Date',
        'no_approval' => 'No Approval',
    ],

    // Status column
    'column' => [
        'label' => 'Approval',
        'no_approval' => 'No Approval',
    ],

    // Resolver config
    'resolver_config' => [
        'users' => 'Users',
        'role' => 'Role',
        'resolver' => 'Resolver',
    ],

    // SLA command
    'sla' => [
        'auto_approved' => 'Auto-approved due to SLA breach',
        'auto_rejected' => 'Auto-rejected due to SLA breach',
    ],

];
