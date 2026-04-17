# AGENTS.md

## Purpose

This repository contains the `coringawc/filament-action-approvals` package.

The package provides action-based approval workflows for Filament v5. It allows any Eloquent model to go through configurable multi-step approval chains with:

- Pluggable approver resolvers (user, role, callback, custom)
- SLA enforcement with automated escalation
- Delegation between approvers
- Full audit trail of all approval actions
- Lifecycle callbacks on the approvable model
- Built-in Filament UI components (resource, relation manager, actions, widgets)

## Architectural Rules

### Trait-First

The integration surface is through traits, not base classes:

- `HasApprovals` — added to any Eloquent model to make it approvable
- `HasApprovalsResource` — added to Filament Edit pages to provide approval header actions

Do not introduce required base model classes. If a feature can be delivered through a trait, method override, or configuration, prefer that approach.

### Polymorphic Design

Approvals are polymorphic (`approvable_type` + `approvable_id`). Any model can use `HasApprovals` regardless of its table structure. Do not add assumptions about the approvable model's schema beyond what Eloquent provides.

### Engine as Single Entry Point

All approval operations (submit, approve, reject, comment, delegate, cancel) go through `ApprovalEngine`. Do not scatter approval state mutations across multiple services or controllers. The engine is the single source of truth for approval state transitions.

```php
$engine = app(ApprovalEngine::class);
$engine->submit($model, $flow, $userId);
$engine->approve($stepInstance, $userId, $comment);
$engine->reject($stepInstance, $userId, $reason);
$engine->comment($approval, $userId, $text);
$engine->delegate($stepInstance, $fromId, $toId, $reason);
$engine->cancel($approval);
```

### Resolver Contract

Approver resolvers implement `Contracts\ApproverResolver` with three methods:

- `resolve(array $config, Model $approvable): array` — returns user IDs
- `label(): string` — human-readable name for the flow builder UI
- `configSchema(): array` — Filament form components for resolver configuration

Built-in resolvers:

| Resolver           | Config                    | Purpose                                               |
| ------------------ | ------------------------- | ----------------------------------------------------- |
| `UserResolver`     | `['user_ids' => [1,2,3]]` | Specific user IDs                                     |
| `RoleResolver`     | `['role' => 'manager']`   | Users by spatie/laravel-permission role               |
| `CallbackResolver` | `['callback' => 'key']`   | Registered closure via `CallbackResolver::register()` |

When adding new resolvers, implement the contract. Do not bypass it.

### Flow → Steps → Step Instances

The data model has a clear separation:

- **ApprovalFlow** — definition (reusable template)
- **ApprovalStep** — definition of each step within a flow (order, resolver, SLA config)
- **Approval** — runtime instance tied to a specific approvable model
- **ApprovalStepInstance** — runtime instance of a step within an approval (tracks status, assigned approvers, received approvals)
- **ApprovalAction** — audit log entry (submitted, approved, rejected, commented, delegated, escalated)
- **ApprovalDelegation** — delegation record (from_user → to_user per step instance)

Do not conflate definition models with runtime models. Flow/Step define the template; Approval/StepInstance track the runtime state.

### Lifecycle Callbacks over Observers

The package calls methods directly on the approvable model (e.g., `onApprovalApproved()`) rather than using Laravel observers or events for model-level reactions. This keeps the integration explicit and visible on the model itself.

Events (`ApprovalSubmitted`, `ApprovalCompleted`, etc.) are fired for cross-cutting concerns (logging, notifications, analytics) but should not be the primary mechanism for domain logic on the approvable model.

### Resubmission Policy

Two methods on the approvable model control submission:

- `allowsApprovalResubmission(): bool` — whether the model can be resubmitted after a completed approval (default: `true`)
- `canSubmitForApproval(?int|string $userId): bool` — whether a specific user can submit (default: `true`)

These are combined in `canBeSubmittedForApproval()` which also checks for pending approvals. Override these methods on the model for custom policies.

### SLA Processing

SLA is processed by the `ProcessApprovalSlaCommand` (signature: `approval:process-sla`), scheduled every minute by default. It:

1. Sends warnings at the configured threshold (default 75%)
2. Processes breaches using the step's `escalation_action`:
   - `Notify` — send notification only
   - `AutoApprove` — auto-approve the step and advance
   - `Reject` — reject the entire approval
   - `Reassign` — re-resolve approvers using the step's resolver

Do not move SLA logic into the main request cycle. It must remain in a scheduled command.

## Plugin Configuration

The `FilamentActionApprovalsPlugin` supports fluent configuration per panel:

```php
FilamentActionApprovalsPlugin::make()
    ->flowResource()                    // Enable/disable the flow CRUD resource
    ->widgets()                         // Enable/disable dashboard widgets
    ->resolvers([...])                  // Override resolvers for this panel
    ->userModel(User::class)            // Override user model for this panel
    ->navigationGroup('Workflow')       // Override navigation group label
```

Static resolution methods prefer plugin config (panel-specific) over config file (global):

- `FilamentActionApprovalsPlugin::resolveUserModel()`
- `FilamentActionApprovalsPlugin::resolveApproverResolvers()`
- `FilamentActionApprovalsPlugin::resolveNavigationGroup()`

### No Hard-Coded User Model

Never `use App\Models\User` directly in runtime code. Always resolve through `FilamentActionApprovalsPlugin::resolveUserModel()` or `config('filament-action-approvals.user_model')`.

### No Runtime Dependency on filament-acl

The `src/` directory must NOT import or reference `coringawc/filament-acl` classes directly. The package is plug-and-play without filament-acl. When filament-acl integration is desired, consumers extend the built-in `ApprovalFlowResource` in their own app and add `HasResourcePermissions` / `#[PermissionSubject]` there. The `filament-acl` package is available as `require-dev` for workbench testing only.

## Filament Components

### Built-in Actions

All five actions are in `src/Actions/`:

| Action                    | Purpose                         | Visibility                                 |
| ------------------------- | ------------------------------- | ------------------------------------------ |
| `SubmitForApprovalAction` | Submits the record for approval | `canBeSubmittedForApproval()` returns true |
| `ApproveAction`           | Approves the current step       | User is assigned approver or delegate      |
| `RejectAction`            | Rejects the approval            | User is assigned approver or delegate      |
| `CommentAction`           | Adds a comment                  | Approval exists and is pending             |
| `DelegateAction`          | Delegates to another user       | User is assigned approver                  |

Use `HasApprovalsResource::getApprovalHeaderActions()` to add all five actions to an Edit page.

### ApprovalStatusColumn

A pre-configured `TextColumn` badge showing the latest approval status. Use it in any table:

```php
ApprovalStatusColumn::make()
```

### ApprovalStatusSection

An infolist section showing approval details, current step progress, and recent activity timeline. Use it in View/Edit pages.

### ApprovalsRelationManager

Shows approval history for the record. Add it to your resource's `getRelations()`.

### Widgets

- `PendingApprovalsWidget` — table of approvals awaiting the current user's action
- `ApprovalAnalyticsWidget` — stats overview (pending, approved, rejected, overdue)

## Multi-Tenancy

When `filament-action-approvals.multi_tenancy.enabled` is `true`:

- `ApprovalFlow::forModel()` scopes by the model's tenant column
- `RoleResolver` optionally scopes users by tenant when `scope_approvers` is true
- The tenant column is added to the `approval_flows` table via migration

## Dependencies

### Runtime

- `filament/filament` ^5.0
- `spatie/laravel-package-tools` ^1.15

### Suggested (optional)

- `coringawc/filament-acl` ^1.0 — enables `HasResourcePermissions` and `PermissionSubject` on `ApprovalFlowResource`
- `spatie/laravel-permission` ^7.0 — required for `RoleResolver`

### Dev

- `orchestra/testbench` — workbench testing
- `phpunit/phpunit` ^12.0
- `larastan/larastan` ^3.0
- `laravel/pint` ^1.0
- `rector/rector` ^2.0

## Translations

The package uses `filament-action-approvals::approval.*` keys for all user-facing strings. Translation files are in `resources/lang/{locale}/approval.php`.

Key groups:

- `status.*` — approval status labels (pending, approved, rejected, cancelled)
- `action_type.*` — action type labels (submitted, approved, rejected, etc.)
- `step_type.*` — step type labels (single, sequential, parallel)
- `step_status.*` — step instance status labels
- `escalation.*` — escalation action labels
- `actions.*` — UI action button labels and messages

## Testing

The workbench contains example models (`PurchaseOrder`, `Expense`) and a full Filament panel for development testing. Tests use Orchestra Testbench with `WithWorkbench`.

```bash
# Run all tests
composer test

# Run with coverage
composer test -- --coverage
```

## Commands

| Command                | Purpose                                                          |
| ---------------------- | ---------------------------------------------------------------- |
| `approval:process-sla` | Process SLA warnings and breaches (runs every minute by default) |
