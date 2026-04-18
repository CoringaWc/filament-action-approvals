# AGENTS.md

## Purpose

This repository contains the `coringawc/filament-action-approvals` package.

The package provides action-based approval workflows for Filament v5. It allows any Eloquent model to go through configurable multi-step approval chains with:

- Pluggable approver resolvers (user, role, callback, custom)
- Approvable actions — domain-specific action keys (submit, cancel, etc.) each with their own flows
- Per-action submit buttons via `actionKey()` — dedicated Filament buttons that skip the selector modal
- SLA enforcement with automated escalation
- Delegation between approvers
- Full audit trail of all approval actions
- Lifecycle callbacks on the approvable model
- Built-in Filament UI components (resource, relation manager, actions, widgets)
- Centralized user display name resolution via `UserDisplayName` (supports `getFilamentName()`)

## Architectural Rules

### Trait-First

The integration surface is through traits, not base classes:

- `HasApprovals` — added to any Eloquent model to make it approvable
- `HasApprovalsResource` — added to Filament Resources to provide `getApprovalHeaderActions()`

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
| `RoleResolver`     | `['role' => 'manager']`   | Users by spatie/laravel-permission role                |
| `CallbackResolver` | `['callback' => 'key']`   | Registered closure via `CallbackResolver::register()` |

When adding new resolvers, implement the contract. Do not bypass it.

### Flow → Steps → Step Instances

The data model has a clear separation:

- **ApprovalFlow** — definition (reusable template). Has `action_key` to tie a flow to a specific approvable action.
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

### Approvable Actions Pattern

Models can define `approvableActions()` to declare domain-specific action keys:

```php
public static function approvableActions(): array
{
    return [
        'submit' => __('Submit for Processing'),
        'cancel' => __('Request Cancellation'),
    ];
}
```

Each action key can have its own `ApprovalFlow` (matched via `approval_flows.action_key`). This enables:
- Multiple independent approval workflows per model type
- Dedicated `SubmitForApprovalAction` buttons per action key via `->actionKey('submit')`
- The generic submit action showing a selector when no `actionKey()` is locked

### Per-Action Submit Buttons (actionKey)

`SubmitForApprovalAction` supports a `->actionKey(string $key)` method:

```php
SubmitForApprovalAction::make('submitPO')
    ->actionKey('submit')  // Locks this button to the 'submit' action
    ->label(__('Submit for Approval'))
```

When `actionKey()` is set:
- The action key selector modal is **skipped** entirely
- Visibility is scoped to flows matching that specific action key
- If one matching flow exists → direct submission (confirmation only)
- If multiple matching flows exist → flow selector only (no action selector)

This allows placing dedicated approval buttons anywhere in the Filament UI (header, action groups, table actions).

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

### User Display Name Resolution

All user-facing name displays go through `Support\UserDisplayName`:

```php
UserDisplayName::resolve($user)         // single user → ?string
UserDisplayName::resolveMany($userIds)  // array of IDs → comma-separated string
```

Resolution order:
1. If user implements `Filament\Models\Contracts\HasName` → `getFilamentName()`
2. Fallback → `getAttribute('name')`

**Never** use `$user->name` or `pluck('name')` directly. Always use `UserDisplayName`.

### No Runtime Dependency on filament-acl

The `src/` directory must NOT import or reference `coringawc/filament-acl` classes directly. The package is plug-and-play without filament-acl. When filament-acl integration is desired, consumers extend the built-in `ApprovalFlowResource` in their own app and add `HasResourcePermissions` / `#[PermissionSubject]` there. The `filament-acl` package is available as `require-dev` for workbench testing only.

## Filament Components

### Built-in Actions

All five actions are in `src/Actions/`:

| Action                    | Purpose                         | Visibility                                                     |
| ------------------------- | ------------------------------- | -------------------------------------------------------------- |
| `SubmitForApprovalAction` | Submits the record for approval | `canBeSubmittedForApproval()` returns true (+ flow exists)     |
| `ApproveAction`           | Approves the current step       | User is assigned approver or delegate                          |
| `RejectAction`            | Rejects the approval            | User is assigned approver or delegate                          |
| `CommentAction`           | Adds a comment                  | Approval exists and is pending                                 |
| `DelegateAction`          | Delegates to another user       | User is assigned approver                                      |

**Usage patterns:**

1. **All at once:** `...static::getResource()::getApprovalHeaderActions()` — adds all 5 actions
2. **Individual:** `ApproveAction::make()`, `RejectAction::make()`, etc. — pick only what you need
3. **Per-action submit:** `SubmitForApprovalAction::make('name')->actionKey('key')` — locked to specific action

### ApprovalStatusColumn

A pre-configured `TextColumn` badge showing the latest approval status. Use it in any table:

```php
ApprovalStatusColumn::make()
```

### ApprovalStatusSection

An infolist section showing approval details, current step progress, and recent activity timeline. Use it in View/Edit pages as an alternative to the relation manager's slide-over:

```php
ApprovalStatusSection::make()
```

> When using `ApprovalsRelationManager`, the section may be redundant — the RM's slide-over shows the same information.

### ApprovalsRelationManager

Shows approval history for the record with a slide-over detail view. Each approval row opens a slide-over containing:

- Approval info (flow, status, submitter, dates)
- Step progress (approver names, received/required counts)
- Audit trail (all actions with timestamps and actors)

Add it to your resource's `getRelations()`.

### Widgets

- `PendingApprovalsWidget` — table of approvals awaiting the current user's action
- `ApprovalAnalyticsWidget` — stats overview (pending, approved, rejected, overdue)

## Filament v5 Component Patterns

### Dot-Notation Rules

In Filament v5, dot-notation in `TextColumn::make()`, `TextEntry::make()`, and `RepeatableEntry::make()` only works for **actual Eloquent relationships**. Regular methods that return models (like `latestApproval()`, `currentApproval()`) must use explicit `->state()` closures.

```php
// CORRECT — flow() is an Eloquent BelongsTo relation on Approval
TextColumn::make('flow.name')

// CORRECT — step() is an Eloquent BelongsTo relation on ApprovalStepInstance
TextColumn::make('step.name')

// WRONG — latestApproval() is a method, not a relation
TextEntry::make('latestApproval.status')

// CORRECT — use explicit state closure for non-relation methods
TextEntry::make('approval_status')
    ->state(fn (Model $record) => $record->latestApproval()?->status?->getLabel())
```

### Reserved Names

The name `actions` is reserved in Filament v5 (used internally by the component system). Never use it as a `make()` name for `RepeatableEntry`, `TextColumn`, or similar components. Use descriptive alternatives like `auditTrail`, `recentActivity`, etc.

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

The workbench contains example models (`PurchaseOrder`, `Expense`, `Invoice`) and a full Filament panel for development testing. Tests use Orchestra Testbench with `WithWorkbench`.

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

## Source Tree

```
src/
├── Actions/
│   ├── ApproveAction.php
│   ├── CommentAction.php
│   ├── DelegateAction.php
│   ├── RejectAction.php
│   └── SubmitForApprovalAction.php
├── ApproverResolvers/
│   ├── CallbackResolver.php
│   ├── RoleResolver.php
│   └── UserResolver.php
├── Columns/
│   └── ApprovalStatusColumn.php
├── Commands/
│   └── ProcessApprovalSlaCommand.php
├── Concerns/
│   ├── HasApprovals.php
│   └── HasApprovalsResource.php
├── Contracts/
│   └── ApproverResolver.php
├── Enums/
│   ├── ActionType.php
│   ├── ApprovalStatus.php
│   ├── EscalationAction.php
│   ├── StepStatus.php
│   └── StepType.php
├── Events/
│   ├── ApprovalCompleted.php
│   ├── ApprovalEscalated.php
│   ├── ApprovalRejected.php
│   ├── ApprovalStepCompleted.php
│   └── ApprovalSubmitted.php
├── Infolists/
│   └── ApprovalStatusSection.php
├── Models/
│   ├── Approval.php
│   ├── ApprovalAction.php
│   ├── ApprovalDelegation.php
│   ├── ApprovalFlow.php
│   ├── ApprovalStep.php
│   └── ApprovalStepInstance.php
├── Notifications/
│   ├── ApprovalApprovedNotification.php
│   ├── ApprovalEscalatedNotification.php
│   ├── ApprovalRejectedNotification.php
│   ├── ApprovalRequestedNotification.php
│   └── ApprovalSlaWarningNotification.php
├── RelationManagers/
│   └── ApprovalsRelationManager.php
├── Resources/
│   └── ApprovalFlowResource.php (+ Pages/)
├── Services/
│   └── ApprovalEngine.php
├── Support/
│   ├── ApprovableModelLabel.php
│   └── UserDisplayName.php
├── Widgets/
│   ├── ApprovalAnalyticsWidget.php
│   └── PendingApprovalsWidget.php
└── FilamentActionApprovalsPlugin.php
```
