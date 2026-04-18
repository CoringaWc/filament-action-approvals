# Filament Action Approvals

[![Latest Version on Packagist](https://img.shields.io/packagist/v/coringawc/filament-action-approvals.svg?style=flat-square)](https://packagist.org/packages/coringawc/filament-action-approvals)
[![License](https://img.shields.io/packagist/l/coringawc/filament-action-approvals.svg?style=flat-square)](https://packagist.org/packages/coringawc/filament-action-approvals)

Action-based approval workflows for [Filament v5](https://filamentphp.com). Define multi-step approval flows with configurable approver resolvers, SLA enforcement, delegation, and lifecycle callbacks — all integrated into the Filament admin panel.

## Features

- **Multi-step approval flows** — sequential steps with configurable approver resolution
- **Polymorphic** — any Eloquent model can be approvable via the `HasApprovals` trait
- **Pluggable approver resolvers** — `UserResolver`, `RoleResolver`, `CallbackResolver`, or create your own
- **Delegation** — approvers can delegate their step to another user
- **SLA enforcement** — per-step SLA deadlines with warning notifications and configurable escalation (notify, auto-approve, reject, reassign)
- **Lifecycle callbacks** — hook into `onApprovalSubmitted`, `onApprovalApproved`, `onApprovalRejected`, etc. directly on your model
- **Resubmission policy** — control whether models can be resubmitted after approval/rejection
- **Built-in Filament components**:
  - `ApprovalFlowResource` — CRUD for managing approval flow definitions
  - `ApprovalsRelationManager` — shows approval history on any approvable model
  - `ApprovalStatusColumn` — ready-to-use status badge column
  - `ApprovalStatusSection` — infolist section with approval details and timeline
  - Header actions: Submit, Approve, Reject, Comment, Delegate
  - Widgets: Pending Approvals, Approval Analytics
- **Multi-tenancy support** — scope flows and approvers per tenant
- **Event-driven** — fires events for submitted, completed, rejected, step-completed, and escalated
- **Notification system** — notifies approvers, submitters, and escalation targets
- **ACL integration** — optional integration with [`coringawc/filament-acl`](https://github.com/CoringaWc/filament-acl) for permission-aware resources

## Requirements

- PHP 8.3+
- Laravel 12+
- Filament 5.x

### Optional

- [`coringawc/filament-acl`](https://github.com/CoringaWc/filament-acl) ^1.0 — enables `HasResourcePermissions` and `PermissionSubject` integration
- [`spatie/laravel-permission`](https://github.com/spatie/laravel-permission) ^7.0 — required for `RoleResolver`

## Installation

```bash
composer require coringawc/filament-action-approvals
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="filament-action-approvals-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="filament-action-approvals-config"
```

## Configuration

The config file (`config/filament-action-approvals.php`) contains:

```php
return [
    // The user model used for approver relationships
    'user_model' => App\Models\User::class,

    // Registered resolver classes available in the flow builder
    'approver_resolvers' => [
        \CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver::class,
        \CoringaWc\FilamentActionApprovals\ApproverResolvers\RoleResolver::class,
        \CoringaWc\FilamentActionApprovals\ApproverResolvers\CallbackResolver::class,
    ],

    // Multi-tenancy settings
    'multi_tenancy' => [
        'enabled' => false,
        'column' => 'company_id',
        'scope_approvers' => true,
    ],

    // SLA warning threshold (0.75 = 75% of SLA time elapsed)
    'sla_warning_threshold' => 0.75,

    // Auto-register the SLA processing command to run every minute
    'schedule_sla_command' => true,

    // Navigation group for the ApprovalFlow resource
    'navigation_group' => 'Approvals',

    // Database table prefix (empty = no prefix)
    'table_prefix' => '',
];
```

## Plugin Registration

Register the plugin in your Filament panel provider:

```php
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentActionApprovalsPlugin::make()
                ->flowResource()      // Enable the ApprovalFlow CRUD resource (default: true)
                ->widgets()           // Enable dashboard widgets (default: true)
                ->navigationGroup('Workflow'),  // Override navigation group
        ]);
}
```

### Plugin Methods

| Method                               | Description                                           |
| ------------------------------------ | ----------------------------------------------------- |
| `flowResource(bool $enabled = true)` | Enable/disable the built-in ApprovalFlow resource     |
| `widgets(bool $enabled = true)`      | Enable/disable PendingApprovals and Analytics widgets |
| `resolvers(array $resolvers)`        | Override approver resolvers for this panel            |
| `userModel(string $model)`           | Override the user model for this panel                |
| `navigationGroup(string $group)`     | Override the navigation group label                   |

## Usage

### 1. Make Your Model Approvable

Add the `HasApprovals` trait to any Eloquent model:

```php
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovals;

class PurchaseOrder extends Model
{
    use HasApprovals;

    // React to approval lifecycle events
    public function onApprovalApproved(Approval $approval): void
    {
        $this->update(['status' => 'approved']);
    }

    public function onApprovalRejected(Approval $approval): void
    {
        $this->update(['status' => 'rejected']);
    }
}
```

### 2. Create an Approval Flow

Approval flows can be created via the admin panel UI (ApprovalFlow resource) or programmatically:

```php
use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\RoleResolver;
use CoringaWc\FilamentActionApprovals\Enums\EscalationAction;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;

$flow = ApprovalFlow::create([
    'name' => 'Purchase Order Approval',
    'approvable_type' => PurchaseOrder::class, // or morph alias
    'is_active' => true,
]);

// Step 1: Manager approval
$flow->steps()->create([
    'name' => 'Manager Review',
    'order' => 1,
    'type' => StepType::Single,
    'approver_resolver' => UserResolver::class,
    'approver_config' => ['user_ids' => [1, 2]],
    'required_approvals' => 1,
    'sla_hours' => 24,
    'escalation_action' => EscalationAction::Notify,
]);

// Step 2: Director approval
$flow->steps()->create([
    'name' => 'Director Sign-off',
    'order' => 2,
    'type' => StepType::Single,
    'approver_resolver' => RoleResolver::class,
    'approver_config' => ['role' => 'director'],
    'required_approvals' => 1,
    'sla_hours' => 48,
    'escalation_action' => EscalationAction::AutoApprove,
]);
```

### 3. Add Approval Actions to Your Resource

Use the `HasApprovalsResource` trait on your Edit page and add the `ApprovalsRelationManager`:

```php
// Resource
use CoringaWc\FilamentActionApprovals\RelationManagers\ApprovalsRelationManager;
use CoringaWc\FilamentActionApprovals\Columns\ApprovalStatusColumn;

class PurchaseOrderResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title'),
            ApprovalStatusColumn::make(), // Shows approval status badge
        ]);
    }

    public static function getRelations(): array
    {
        return [
            ApprovalsRelationManager::class,
        ];
    }
}

// Edit Page — trait goes on the Resource, not the Page
class EditPurchaseOrder extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            ...static::getResource()::getApprovalHeaderActions(),
            // Submit, Approve, Reject, Comment, Delegate buttons
        ];
    }
}
```

### 4. Programmatic Usage

You can also interact with the approval engine directly:

```php
use CoringaWc\FilamentActionApprovals\Services\ApprovalEngine;

$engine = app(ApprovalEngine::class);

// Submit for approval
$approval = $engine->submit($purchaseOrder, $flow, auth()->id());

// Or via the model
$approval = $purchaseOrder->submitForApproval($flow);

// Approve a step
$stepInstance = $approval->currentStepInstance();
$engine->approve($stepInstance, auth()->id(), 'Approved — budget ok');

// Reject
$engine->reject($stepInstance, auth()->id(), 'Budget exceeded');

// Add a comment
$engine->comment($approval, auth()->id(), 'Please provide receipts');

// Delegate to another user
$engine->delegate($stepInstance, auth()->id(), $delegateUserId, 'On vacation');

// Cancel the approval
$engine->cancel($approval);
```

## Approver Resolvers

### UserResolver

Resolves specific user IDs as approvers:

```php
'approver_resolver' => UserResolver::class,
'approver_config' => ['user_ids' => [1, 2, 3]],
```

### RoleResolver

Resolves all users with a given role (requires `spatie/laravel-permission`):

```php
'approver_resolver' => RoleResolver::class,
'approver_config' => ['role' => 'manager'],
```

### CallbackResolver

Register custom resolution logic:

```php
use CoringaWc\FilamentActionApprovals\ApproverResolvers\CallbackResolver;

// Register in a service provider
CallbackResolver::register('department_head', function (array $config, Model $approvable) {
    return [$approvable->department->head_id];
});

// Use in a step
'approver_resolver' => CallbackResolver::class,
'approver_config' => ['callback' => 'department_head'],
```

### Custom Resolver

Implement the `ApproverResolver` contract:

```php
use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;

class HierarchyResolver implements ApproverResolver
{
    public function resolve(array $config, Model $approvable): array
    {
        return [$approvable->user->manager_id];
    }

    public function label(): string
    {
        return 'Direct Manager';
    }

    public function configSchema(): array
    {
        return []; // Filament form components for the flow builder
    }
}
```

Register it in the config or plugin:

```php
// config
'approver_resolvers' => [
    UserResolver::class,
    RoleResolver::class,
    HierarchyResolver::class,
],

// or per-panel
FilamentActionApprovalsPlugin::make()
    ->resolvers([UserResolver::class, HierarchyResolver::class])
```

## Lifecycle Callbacks

Override these methods on your model to react to approval events:

| Method                                                      | Called When                            |
| ----------------------------------------------------------- | -------------------------------------- |
| `onApprovalSubmitted(Approval $approval)`                   | Model is submitted for approval        |
| `onApprovalApproved(Approval $approval)`                    | All steps approved (approval complete) |
| `onApprovalRejected(Approval $approval)`                    | Approval rejected at any step          |
| `onApprovalCancelled(Approval $approval)`                   | Approval is cancelled                  |
| `onApprovalCommented(ApprovalAction $action)`               | Comment added                          |
| `onApprovalDelegated(ApprovalStepInstance $si, $from, $to)` | Step delegated                         |
| `onApprovalStepCompleted(ApprovalStepInstance $si)`         | Individual step approved               |
| `onApprovalEscalated(ApprovalStepInstance $si)`             | SLA breach triggers escalation         |

## Resubmission Policy

Control whether a model can be resubmitted after approval or rejection:

```php
class Expense extends Model
{
    use HasApprovals;

    // Block resubmission after the expense is approved
    public function allowsApprovalResubmission(): bool
    {
        $latest = $this->latestApproval();
        return !$latest || $latest->status !== ApprovalStatus::Approved;
    }

    // Restrict who can submit
    public function canSubmitForApproval(int|string|null $userId = null): bool
    {
        return $this->user_id === ($userId ?? auth()->id());
    }
}
```

## SLA Enforcement

Configure per-step SLA deadlines:

```php
$flow->steps()->create([
    'name' => 'Urgent Review',
    'sla_hours' => 4,
    'escalation_action' => EscalationAction::AutoApprove,
    // ...
]);
```

### Escalation Actions

| Action        | Behavior                                             |
| ------------- | ---------------------------------------------------- |
| `Notify`      | Sends an `ApprovalEscalatedNotification`             |
| `AutoApprove` | Automatically approves the overdue step              |
| `Reject`      | Automatically rejects the approval                   |
| `Reassign`    | Reassigns to new approvers using the step's resolver |

The SLA processor runs via the `approval:process-sla` command, automatically scheduled every minute when `schedule_sla_command` is `true`.

## Events

| Event                   | Payload                              |
| ----------------------- | ------------------------------------ |
| `ApprovalSubmitted`     | `Approval $approval`                 |
| `ApprovalCompleted`     | `Approval $approval`                 |
| `ApprovalRejected`      | `Approval $approval`                 |
| `ApprovalStepCompleted` | `ApprovalStepInstance $stepInstance` |
| `ApprovalEscalated`     | `ApprovalStepInstance $stepInstance` |

## Multi-Tenancy

Enable tenant-scoped approval flows:

```php
// config/filament-action-approvals.php
'multi_tenancy' => [
    'enabled' => true,
    'column' => 'company_id',      // Your tenant foreign key
    'scope_approvers' => true,     // Also scope role-based resolvers
],
```

When enabled, `ApprovalFlow::forModel()` automatically scopes queries by the model's tenant column.

## Database Schema

The package creates the following tables:

| Table                     | Purpose                                                                           |
| ------------------------- | --------------------------------------------------------------------------------- |
| `approval_flows`          | Flow definitions (name, approvable_type, active status)                           |
| `approval_steps`          | Step definitions within a flow (order, resolver, SLA, escalation)                 |
| `approvals`               | Runtime approval instances (polymorphic to approvable model)                      |
| `approval_step_instances` | Runtime step instances (status, assigned approvers, SLA tracking)                 |
| `approval_actions`        | Audit trail of all actions (submit, approve, reject, comment, delegate, escalate) |
| `approval_delegations`    | Delegation records (from_user to_user per step instance)                          |

## Integration with filament-acl (Optional)

If you use [`coringawc/filament-acl`](https://github.com/CoringaWc/filament-acl), you can extend the built-in `ApprovalFlowResource` to add permission-aware access control:

```bash
composer require coringawc/filament-acl
```

Create a custom resource that extends the package resource:

```php
namespace App\Filament\Admin\Resources;

use CoringaWc\FilamentAcl\Attributes\PermissionSubject;
use CoringaWc\FilamentAcl\Resources\Concerns\HasResourcePermissions;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource as BaseResource;

#[PermissionSubject('ApprovalFlow')]
class ApprovalFlowResource extends BaseResource
{
    use HasResourcePermissions;
}
```

Then disable the built-in resource on the plugin and register your own:

```php
FilamentActionApprovalsPlugin::make()
    ->flowResource(false) // Disable built-in resource
```

## Testing

The package includes a workbench environment for development and testing:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
