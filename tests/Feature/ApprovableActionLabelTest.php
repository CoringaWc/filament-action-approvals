<?php

declare(strict_types=1);

use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use Workbench\App\Enums\ExpenseApprovableAction;
use Workbench\App\Models\Expense;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\PurchaseOrder;
use Workbench\App\Models\User;

// ─── optionsFor ───────────────────────────────────────────────

it('returns options for model with approvableActions method override', function (): void {
    $options = ApprovableActionLabel::optionsFor(PurchaseOrder::class);

    expect($options)
        ->toHaveKey('submit')
        ->toHaveKey('cancel');
});

it('returns options from #[ApprovableActions] attribute with enum', function (): void {
    $options = ApprovableActionLabel::optionsFor(Expense::class);

    expect($options)
        ->toHaveKey('submit')
        ->toHaveKey('reimburse')
        ->and($options['submit'])->toBe('Submit for Approval')
        ->and($options['reimburse'])->toBe('Request Reimbursement');
});

it('returns empty for null model', function (): void {
    expect(ApprovableActionLabel::optionsFor(null))->toBe([]);
});

it('returns empty for model class without approvableActions', function (): void {
    expect(ApprovableActionLabel::optionsFor(User::class))->toBe([]);
});

it('returns empty for blank string', function (): void {
    expect(ApprovableActionLabel::optionsFor(''))->toBe([]);
});

it('resolves model from instance', function (): void {
    $order = PurchaseOrder::factory()->create();
    $options = ApprovableActionLabel::optionsFor($order);

    expect($options)->toHaveKey('submit');
});

// ─── hasOptionsFor ────────────────────────────────────────────

it('returns true when model has options', function (): void {
    expect(ApprovableActionLabel::hasOptionsFor(PurchaseOrder::class))->toBeTrue();
});

it('returns false when model has no options', function (): void {
    expect(ApprovableActionLabel::hasOptionsFor(null))->toBeFalse();
});

// ─── resolve ──────────────────────────────────────────────────

it('resolves label for existing action key', function (): void {
    $label = ApprovableActionLabel::resolve(PurchaseOrder::class, 'submit');

    expect($label)->not->toBeEmpty();
});

it('returns headline fallback for unknown action key', function (): void {
    $label = ApprovableActionLabel::resolve(PurchaseOrder::class, 'some_unknown_action');

    expect($label)->toBe('Some Unknown Action');
});

it('returns any action translation for null key', function (): void {
    $label = ApprovableActionLabel::resolve(PurchaseOrder::class, null);

    expect($label)->toBe(__('filament-action-approvals::approval.flow.any_action'));
});

// ─── Enum-based actions via #[ApprovableActions] attribute ────

it('returns enum class for model using #[ApprovableActions] with enum', function (): void {
    expect(ApprovableActionLabel::enumClassFor(Expense::class))->toBe(ExpenseApprovableAction::class);
});

it('returns null enum class for model with method override (no attribute)', function (): void {
    expect(ApprovableActionLabel::enumClassFor(PurchaseOrder::class))->toBeNull();
});

it('returns null enum class for model with HasStateApprovals (no attribute)', function (): void {
    expect(ApprovableActionLabel::enumClassFor(Invoice::class))->toBeNull();
});

it('resolves enum case for action key', function (): void {
    $case = ApprovableActionLabel::resolveEnum(Expense::class, 'submit');

    expect($case)->toBe(ExpenseApprovableAction::Submit);
});

it('returns null enum case for model without enum', function (): void {
    $case = ApprovableActionLabel::resolveEnum(PurchaseOrder::class, 'submit');

    expect($case)->toBeNull();
});

it('returns null enum case for null action key', function (): void {
    expect(ApprovableActionLabel::resolveEnum(Expense::class, null))->toBeNull();
});

// ─── iconFor / colorFor ───────────────────────────────────────

it('returns icon from enum implementing HasIcon', function (): void {
    $icon = ApprovableActionLabel::iconFor(Expense::class, 'submit');

    expect($icon)->toBe('heroicon-o-paper-airplane');
});

it('returns null icon for model without enum', function (): void {
    expect(ApprovableActionLabel::iconFor(PurchaseOrder::class, 'submit'))->toBeNull();
});

it('returns color from enum implementing HasColor', function (): void {
    $color = ApprovableActionLabel::colorFor(Expense::class, 'reimburse');

    expect($color)->toBe('success');
});

it('returns null color for model without enum', function (): void {
    expect(ApprovableActionLabel::colorFor(PurchaseOrder::class, 'submit'))->toBeNull();
});

it('returns null icon for null action key', function (): void {
    expect(ApprovableActionLabel::iconFor(Expense::class, null))->toBeNull();
});
