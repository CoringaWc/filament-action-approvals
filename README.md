# Filament Action Approvals

[![Latest Version on Packagist](https://img.shields.io/packagist/v/coringawc/filament-action-approvals.svg?style=flat-square)](https://packagist.org/packages/coringawc/filament-action-approvals)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/coringawc/filament-action-approvals/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/coringawc/filament-action-approvals/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/coringawc/filament-action-approvals/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/coringawc/filament-action-approvals/actions?query=workflow%3A%22Fix+PHP+code+styling%22+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/coringawc/filament-action-approvals.svg?style=flat-square)](https://packagist.org/packages/coringawc/filament-action-approvals)

Action-based approval workflows for [Filament v5](https://filamentphp.com). Define multi-step approval flows with configurable approver resolvers, SLA enforcement, delegation, and lifecycle callbacks — all integrated into the Filament admin panel.

## Features

- **Multi-step approval flows** — sequential steps with configurable approver resolution
- **Polymorphic** — any Eloquent model can be approvable via the `HasApprovals` trait
- **Pluggable approver resolvers** — `UserResolver`, `RoleResolver`, `CallbackResolver`, or create your own
- **Approvable actions** — define domain-specific actions (submit, cancel, etc.) on your model, each with its own approval flow
- **Per-action submit buttons** — create dedicated `SubmitForApprovalAction` instances locked to a specific `actionKey`
- **Delegation** — approvers can delegate their step to another user
- **SLA enforcement** — per-step SLA deadlines with warning notifications and configurable escalation (notify, auto-approve, reject, reassign)
- **Lifecycle callbacks** — hook into `onApprovalSubmitted`, `onApprovalApproved`, `onApprovalRejected`, etc. directly on your model
- **Resubmission policy** — control whether models can be resubmitted after approval/rejection
- **User display name** — uses Filament's `getFilamentName()` when available, falls back to `name` attribute
- **Built-in Filament components**:
  - `ApprovalFlowResource` — CRUD for managing approval flow definitions
  - `ApprovalsRelationManager` — shows approval history with slide-over details
  - `ApprovalStatusColumn` — ready-to-use status badge column
  - `ApprovalStatusSection` — infolist section with approval details and timeline
  - Header actions: Submit, Approve, Reject, Comment, Delegate (usable individually or as a group)
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

    // Date display settings
    'date' => [
        'display_format' => 'd/m/Y H:i',
        'use_since' => true,
    ],

    // Auto-register the SLA processing command to run every minute
    'schedule_sla_command' => true,

    // Navigation group for the ApprovalFlow resource
    'navigation_group' => null,

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
use CoringaWc\FilamentActionApprovals\Models\Approval;

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

### 2. Define Approvable Actions (Optional)

If your model has multiple domain actions that require approval (e.g., submit, cancel, reimburse), define them via `approvableActions()`:

```php
class PurchaseOrder extends Model
{
    use HasApprovals;

    /**
     * Define domain-specific actions that can be submitted for approval.
     * Each key is an action identifier, each value is a human-readable label.
     *
     * @return array<string, string>
     */
    public static function approvableActions(): array
    {
        return [
            'submit' => __('Submit for Processing'),
            'cancel' => __('Request Cancellation'),
        ];
    }
}
```

When `approvableActions()` is defined, the `SubmitForApprovalAction` will show a selector for the user to pick which action they are requesting. You can also create dedicated buttons per action — see [Per-Action Submit Buttons](#per-action-submit-buttons).

### 3. Create an Approval Flow

Approval flows can be created via the admin panel UI (ApprovalFlow resource) or programmatically:

```php
use CoringaWc\FilamentActionApprovals\ApproverResolvers\UserResolver;
use CoringaWc\FilamentActionApprovals\ApproverResolvers\RoleResolver;
use CoringaWc\FilamentActionApprovals\Enums\EscalationAction;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;

$flow = ApprovalFlow::create([
    'name' => 'Purchase Order Approval',
    'approvable_type' => PurchaseOrder::class,
    'action_key' => 'submit',  // Optional: tie this flow to a specific action
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

### 4. Add Approval Actions to Your Resource

#### Option A: All actions at once (quickstart)

Use the `HasApprovalsResource` trait to add all 5 approval actions as header actions:

```php
use CoringaWc\FilamentActionApprovals\Concerns\HasApprovalsResource;

class PurchaseOrderResource extends Resource
{
    use HasApprovalsResource;
    // ...
}

// Edit or View Page
class EditPurchaseOrder extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            ...static::getResource()::getApprovalHeaderActions(),
            // Returns: SubmitForApprovalAction, ApproveAction, RejectAction,
            //          CommentAction, DelegateAction
        ];
    }
}
```

#### Option B: Individual actions (fine-grained control)

Use each action individually when you need to customize visibility, labels, or only show specific actions:

```php
use CoringaWc\FilamentActionApprovals\Actions\ApproveAction;
use CoringaWc\FilamentActionApprovals\Actions\CommentAction;
use CoringaWc\FilamentActionApprovals\Actions\DelegateAction;
use CoringaWc\FilamentActionApprovals\Actions\RejectAction;
use CoringaWc\FilamentActionApprovals\Actions\SubmitForApprovalAction;

class ViewInvoice extends ViewRecord
{
    protected function getHeaderActions(): array
    {
        return [
            // Custom domain actions first
            AdvanceStatusAction::make(),
            CancelAction::make(),

            // Only the approval response actions — submitter uses a different flow
            ApproveAction::make(),
            RejectAction::make(),
            CommentAction::make(),
            DelegateAction::make(),
        ];
    }
}
```

Each action manages its own visibility automatically:

| Action                    | Visible When                                               |
| ------------------------- | ---------------------------------------------------------- |
| `SubmitForApprovalAction` | Record can be submitted (no pending approval, flows exist) |
| `ApproveAction`           | User is an assigned approver and hasn't acted yet          |
| `RejectAction`            | User is an assigned approver and hasn't acted yet          |
| `CommentAction`           | A pending approval exists and user can act on it           |
| `DelegateAction`          | User is an assigned approver (original, not delegate)      |

#### Per-Action Submit Buttons

When your model defines `approvableActions()`, you can create dedicated submit buttons for each action using `actionKey()`:

```php
use CoringaWc\FilamentActionApprovals\Actions\SubmitForApprovalAction;
use Filament\Support\Icons\Heroicon;

class EditPurchaseOrder extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            // Dedicated button for "submit" action — skips the action selector modal
            SubmitForApprovalAction::make('submitPO')
                ->actionKey('submit')
                ->label(__('Submit for Approval'))
                ->icon(Heroicon::OutlinedPaperAirplane),

            // Dedicated button for "cancel" action
            SubmitForApprovalAction::make('cancelPO')
                ->actionKey('cancel')
                ->label(__('Request Cancellation'))
                ->icon(Heroicon::OutlinedXMark)
                ->color('danger'),

            // Approval response actions
            ApproveAction::make(),
            RejectAction::make(),
            CommentAction::make(),
            DelegateAction::make(),
        ];
    }
}
```

When `actionKey()` is set:

- The action key selector modal is **skipped** entirely
- The action is only visible when there are matching flows for that specific `actionKey`
- If there's exactly one matching flow, submission happens with just a confirmation dialog (no form)
- If there are multiple matching flows, only the flow selector is shown (without the action selector)

This gives you full control to place specific approval submission buttons anywhere in your UI — in the header, in a custom action group, or even as table row actions.

### 5. Add the Relation Manager

Add the `ApprovalsRelationManager` to show approval history on any resource:

```php
use CoringaWc\FilamentActionApprovals\RelationManagers\ApprovalsRelationManager;

class PurchaseOrderResource extends Resource
{
    public static function getRelations(): array
    {
        return [
            ApprovalsRelationManager::class,
        ];
    }
}
```

The relation manager shows a table of all approvals for the record. Each row has a "View" action that opens a **slide-over** with:

- Approval details (flow, status, submitter, dates)
- Step-by-step progress with approver names and received/required counts
- Full audit trail (submitted, approved, rejected, commented, delegated, escalated)

### 6. Add the Status Column

Show the latest approval status in any table:

```php
use CoringaWc\FilamentActionApprovals\Columns\ApprovalStatusColumn;

class PurchaseOrderResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title'),
            ApprovalStatusColumn::make(), // Badge: Pending, Approved, Rejected, Cancelled
        ]);
    }
}
```

### 7. Add the Status Section to Infolists (Optional)

Use `ApprovalStatusSection` to display approval details inline on a View or Edit page. This is an alternative to the relation manager — useful when you want the approval details embedded directly in the page's infolist rather than in a separate tab or slide-over.

```php
use CoringaWc\FilamentActionApprovals\Infolists\ApprovalStatusSection;

class ViewPurchaseOrder extends ViewRecord
{
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // ... your other infolist sections ...
            ApprovalStatusSection::make(),
        ]);
    }
}
```

> **Note:** When using `ApprovalsRelationManager`, the `ApprovalStatusSection` may be redundant since the RM's slide-over already shows full approval details.

### 8. Programmatic Usage

Interact with the approval engine directly:

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

## User Display Names

The package uses `UserDisplayName::resolve()` to display user names throughout the UI (infolists, columns, selects, audit trail). The resolution order is:

1. If the user model implements `Filament\Models\Contracts\HasName`, calls `getFilamentName()`
2. Falls back to the `name` attribute

This means if your User model uses Filament's `HasName` interface (which provides `getFilamentName()`), the package will automatically use the display name defined there (e.g., full name, username, or any custom format).

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;

class User extends Authenticatable implements FilamentUser, HasName
{
    public function getFilamentName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
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

    public static function label(): string
    {
        return 'Direct Manager';
    }

    public static function configSchema(): array
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
| `ApprovalStepCompleted` | `ApprovalStepInstance $stepInstance`  |
| `ApprovalEscalated`     | `ApprovalStepInstance $stepInstance`  |

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
| `approval_flows`          | Flow definitions (name, approvable_type, action_key, active status)               |
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

## Translations

The package uses `filament-action-approvals::approval.*` keys for all user-facing strings. Translation files are in `resources/lang/{locale}/approval.php`.

Key groups:

| Group          | Description                                          |
| -------------- | ---------------------------------------------------- |
| `status.*`     | Approval status labels (pending, approved, etc.)     |
| `action_type.*`| Action type labels (submitted, approved, etc.)       |
| `step_type.*`  | Step type labels (single, sequential, parallel)      |
| `step_status.*`| Step instance status labels                          |
| `escalation.*` | Escalation action labels                             |
| `actions.*`    | UI action button labels and messages                 |

To publish translations for customization:

```bash
php artisan vendor:publish --tag="filament-action-approvals-translations"
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
