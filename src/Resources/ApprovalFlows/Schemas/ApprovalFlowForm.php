<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Schemas;

use CoringaWc\FilamentActionApprovals\Concerns\HasApprovals;
use CoringaWc\FilamentActionApprovals\Contracts\ApproverResolver;
use CoringaWc\FilamentActionApprovals\Enums\EscalationAction;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use CoringaWc\FilamentActionApprovals\Support\FormFieldHint;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ApprovalFlowForm
{
    public static function configure(Schema $schema): Schema
    {
        $resolvers = FilamentActionApprovalsPlugin::resolveApproverResolvers();

        return $schema->components([
            Section::make(__('filament-action-approvals::approval.flow.flow_details'))
                ->schema([
                    FormFieldHint::apply(
                        TextInput::make('name')
                            ->label(__('filament-action-approvals::approval.flow.name'))
                            ->required()
                            ->maxLength(255),
                        __('filament-action-approvals::approval.flow_hints.name'),
                    ),
                    FormFieldHint::apply(
                        Textarea::make('description')
                            ->label(__('filament-action-approvals::approval.flow.description'))
                            ->rows(2),
                        __('filament-action-approvals::approval.flow_hints.description'),
                    ),
                    FormFieldHint::apply(
                        TranslatableSelect::apply(
                            Select::make('approvable_type')
                                ->label(__('filament-action-approvals::approval.flow.applies_to'))
                                ->options(fn (): array => static::getApprovableModels())
                                ->placeholder(__('filament-action-approvals::approval.flow.any_model'))
                                ->searchable()
                                ->live()
                                ->partiallyRenderComponentsAfterStateUpdated(['action_key', 'steps'])
                                ->afterStateUpdated(fn (Set $set) => $set('action_key', null))
                                ->helperText(__('filament-action-approvals::approval.flow.applies_to_helper')),
                        ),
                        __('filament-action-approvals::approval.flow_hints.applies_to'),
                    ),
                    FormFieldHint::apply(
                        static::makeActionKeySelect(),
                        __('filament-action-approvals::approval.flow_hints.action_key'),
                    ),
                    FormFieldHint::apply(
                        Toggle::make('is_active')
                            ->label(__('filament-action-approvals::approval.flow.is_active'))
                            ->default(true),
                        __('filament-action-approvals::approval.flow_hints.is_active'),
                    ),
                ])
                ->columns(2),

            Section::make(__('filament-action-approvals::approval.flow.approval_steps'))
                ->schema([
                    FormFieldHint::apply(
                        Repeater::make('steps')
                            ->label(__('filament-action-approvals::approval.flow_table.steps'))
                            ->relationship()
                            ->orderColumn('order')
                            ->schema(static::stepSchema($resolvers))
                            ->columns(2)
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(1)
                            ->addActionLabel(__('filament-action-approvals::approval.flow.add_step'))
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => static::normalizeStepData($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => static::normalizeStepData($data)),
                        __('filament-action-approvals::approval.flow_hints.steps'),
                    ),
                ]),
        ]);
    }

    /**
     * @param  array<class-string<ApproverResolver>>  $resolvers
     * @return array<int, mixed>
     */
    protected static function stepSchema(array $resolvers): array
    {
        return [
            FormFieldHint::apply(
                TextInput::make('name')
                    ->label(__('filament-action-approvals::approval.flow.step_name'))
                    ->required()
                    ->columnSpan(2),
                __('filament-action-approvals::approval.flow_hints.step_name'),
            ),
            FormFieldHint::apply(
                Select::make('type')
                    ->label(__('filament-action-approvals::approval.flow.type'))
                    ->options(StepType::class)
                    ->default('single')
                    ->required()
                    ->live()
                    ->partiallyRenderComponentsAfterStateUpdated(['required_approvals']),
                __('filament-action-approvals::approval.flow_hints.type'),
            ),
            FormFieldHint::apply(
                Select::make('approver_resolver')
                    ->label(__('filament-action-approvals::approval.flow.approver_type'))
                    ->options(fn (Get $get): array => static::getFilteredResolverOptions(
                        $resolvers,
                        $get('../../approvable_type'),
                    ))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('approver_config', [])),
                __('filament-action-approvals::approval.flow_hints.approver_type'),
            ),
            ...static::buildResolverConfigFields($resolvers),
            FormFieldHint::apply(
                TextInput::make('required_approvals')
                    ->label(__('filament-action-approvals::approval.flow.required_approvals'))
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->visible(fn (Get $get): bool => $get('type') === 'parallel')
                    ->helperText(function (Get $get): string {
                        $config = [];

                        foreach (['user_ids', 'role', 'custom_rule'] as $key) {
                            $value = $get('approver_config.'.$key);

                            if ($value !== null) {
                                $config[$key] = $value;
                            }
                        }

                        $count = null;

                        if ($config !== []) {
                            foreach ($config as $value) {
                                if (is_array($value)) {
                                    $count = count($value);
                                    break;
                                }
                            }
                        }

                        if ($count) {
                            $required = $get('required_approvals') ?: 1;

                            return __('filament-action-approvals::approval.flow.required_approvals_hint', ['required' => $required, 'total' => $count]);
                        }

                        return __('filament-action-approvals::approval.flow.required_approvals_helper');
                    })
                    ->live()
                    ->partiallyRenderAfterStateUpdated(),
                __('filament-action-approvals::approval.flow_hints.required_approvals'),
            ),
            FormFieldHint::apply(
                TextInput::make('sla_hours')
                    ->numeric()
                    ->label(__('filament-action-approvals::approval.flow.sla_hours'))
                    ->helperText(__('filament-action-approvals::approval.flow.sla_helper'))
                    ->live()
                    ->partiallyRenderComponentsAfterStateUpdated(['escalation_action']),
                __('filament-action-approvals::approval.flow_hints.sla_hours'),
            ),
            FormFieldHint::apply(
                Select::make('escalation_action')
                    ->label(__('filament-action-approvals::approval.flow.escalation_action'))
                    ->options(EscalationAction::class)
                    ->visible(fn (Get $get): bool => filled($get('sla_hours'))),
                __('filament-action-approvals::approval.flow_hints.escalation_action'),
            ),
        ];
    }

    /**
     * @param  array<class-string<ApproverResolver>>  $resolvers
     * @return array<Group>
     */
    protected static function buildResolverConfigFields(array $resolvers): array
    {
        $groups = [];

        foreach ($resolvers as $resolverClass) {
            $fields = $resolverClass::configSchema();

            if ($fields === []) {
                continue;
            }

            $groups[] = Group::make()
                ->schema($fields)
                ->visible(fn (Get $get): bool => $get('approver_resolver') === $resolverClass)
                ->columnSpan(2);
        }

        return $groups;
    }

    /**
     * @param  array<class-string<ApproverResolver>>  $resolvers
     * @return array<string, string>
     */
    protected static function getFilteredResolverOptions(array $resolvers, ?string $modelClass): array
    {
        if (($modelClass !== null) && (! class_exists($modelClass))) {
            $modelClass = null;
        }

        /** @var class-string|null $modelClass */
        $options = [];

        foreach ($resolvers as $resolverClass) {
            if (! $resolverClass::isAvailable($modelClass)) {
                continue;
            }

            $options[$resolverClass] = $resolverClass::label();
        }

        return $options;
    }

    protected static function makeActionKeySelect(): Select
    {
        return TranslatableSelect::apply(
            Select::make('action_key')
                ->label(__('filament-action-approvals::approval.flow.action_key'))
                ->options(fn (Get $get): array => ApprovableActionLabel::optionsFor($get('approvable_type')))
                ->placeholder(__('filament-action-approvals::approval.flow.any_action'))
                ->searchable()
                ->nullable()
                ->visible(fn (Get $get): bool => ApprovableActionLabel::hasOptionsFor($get('approvable_type')))
                ->helperText(__('filament-action-approvals::approval.flow.action_key_helper'))
                ->noOptionsMessage(fn (Get $get): string => blank($get('approvable_type'))
                    ? __('filament-action-approvals::approval.flow.select_model_first')
                    : __('filament-action-approvals::approval.select.no_options')),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function normalizeStepData(array $data): array
    {
        if (is_array($data['approver_config'] ?? null) && $data['approver_config'] !== []) {
            return $data;
        }

        $config = [];

        foreach ($data as $key => $value) {
            if (! str_starts_with($key, 'approver_config.')) {
                continue;
            }

            $configKey = str_replace('approver_config.', '', $key);
            $config[$configKey] = $value;
            unset($data[$key]);
        }

        $data['approver_config'] = $config;

        return $data;
    }

    /**
     * @return array<string, string>
     */
    protected static function getApprovableModels(): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $models = [];

        if (! $panel) {
            return $models;
        }

        foreach ($panel->getResources() as $resource) {
            $modelClass = $resource::getModel();

            if (in_array(HasApprovals::class, class_uses_recursive($modelClass))) {
                $models[$modelClass] = $resource::getModelLabel();
            }
        }

        return $models;
    }
}
