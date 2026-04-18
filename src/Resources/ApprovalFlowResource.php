<?php

namespace CoringaWc\FilamentActionApprovals\Resources;

use CoringaWc\FilamentActionApprovals\Concerns\HasApprovals;
use CoringaWc\FilamentActionApprovals\Enums\EscalationAction;
use CoringaWc\FilamentActionApprovals\Enums\StepType;
use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource\Pages\CreateApprovalFlow;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource\Pages\EditApprovalFlow;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlowResource\Pages\ListApprovalFlows;
use CoringaWc\FilamentActionApprovals\Support\ApprovableActionLabel;
use CoringaWc\FilamentActionApprovals\Support\ApprovableModelLabel;
use CoringaWc\FilamentActionApprovals\Support\DateDisplay;
use CoringaWc\FilamentActionApprovals\Support\FormFieldHint;
use CoringaWc\FilamentActionApprovals\Support\TranslatableSelect;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApprovalFlowResource extends Resource
{
    protected static ?string $model = ApprovalFlow::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    public static function getModelLabel(): string
    {
        return __('filament-action-approvals::approval.flow_resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-action-approvals::approval.flow_resource_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentActionApprovalsPlugin::resolveNavigationGroup();
    }

    public static function form(Schema $schema): Schema
    {
        $resolvers = FilamentActionApprovalsPlugin::resolveApproverResolvers();

        return $schema->components([
            Section::make(__('filament-action-approvals::approval.flow.flow_details'))->schema([
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
                            ->options(fn () => static::getApprovableModels())
                            ->placeholder(__('filament-action-approvals::approval.flow.any_model'))
                            ->searchable()
                            ->live()
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
            ])->columns(2),

            Section::make(__('filament-action-approvals::approval.flow.approval_steps'))->schema([
                FormFieldHint::apply(
                    Repeater::make('steps')
                        ->label(__('filament-action-approvals::approval.flow_table.steps'))
                        ->relationship()
                        ->orderColumn('order')
                        ->schema(fn () => [
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
                                    ->live(),
                                __('filament-action-approvals::approval.flow_hints.type'),
                            ),
                            FormFieldHint::apply(
                                Select::make('approver_resolver')
                                    ->label(__('filament-action-approvals::approval.flow.approver_type'))
                                    ->options(static::getResolverOptions($resolvers))
                                    ->required()
                                    ->live(),
                                __('filament-action-approvals::approval.flow_hints.approver_type'),
                            ),

                            // Dynamic config fields from the selected resolver
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

                                        foreach (['user_ids', 'admin_ids', 'role', 'callback'] as $key) {
                                            $value = $get('approver_config.'.$key);

                                            if ($value !== null) {
                                                $config[$key] = $value;
                                            }
                                        }

                                        $count = null;

                                        if (! empty($config)) {
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
                                    ->live(),
                                __('filament-action-approvals::approval.flow_hints.required_approvals'),
                            ),
                            FormFieldHint::apply(
                                TextInput::make('sla_hours')
                                    ->numeric()
                                    ->label(__('filament-action-approvals::approval.flow.sla_hours'))
                                    ->helperText(__('filament-action-approvals::approval.flow.sla_helper'))
                                    ->live(),
                                __('filament-action-approvals::approval.flow_hints.sla_hours'),
                            ),
                            FormFieldHint::apply(
                                Select::make('escalation_action')
                                    ->label(__('filament-action-approvals::approval.flow.escalation_action'))
                                    ->options(EscalationAction::class)
                                    ->visible(fn (Get $get): bool => filled($get('sla_hours'))),
                                __('filament-action-approvals::approval.flow_hints.escalation_action'),
                            ),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(1)
                        ->addActionLabel(__('filament-action-approvals::approval.flow.add_step'))
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                            return static::normalizeStepData($data);
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                            return static::normalizeStepData($data);
                        }),
                    __('filament-action-approvals::approval.flow_hints.steps'),
                ),
            ]),
        ]);
    }

    /**
     * Build a Group per resolver, each visible only when that resolver is selected.
     * The fields inside use `approver_config.xxx` dot notation to write into the JSON column.
     *
     * @param  array<class-string>  $resolvers
     * @return array<Group>
     */
    protected static function buildResolverConfigFields(array $resolvers): array
    {
        $groups = [];

        foreach ($resolvers as $resolverClass) {
            $fields = $resolverClass::configSchema();

            if (empty($fields)) {
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
     * @param  array<class-string>  $resolvers
     * @return array<string, string>
     */
    protected static function getResolverOptions(array $resolvers): array
    {
        $options = [];

        foreach ($resolvers as $resolverClass) {
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
     * Ensure approver_config is properly structured as an array.
     * Handles both dot-notation expansion and missing values.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function normalizeStepData(array $data): array
    {
        // If approver_config is already a proper array with content, keep it
        if (is_array($data['approver_config'] ?? null) && ! empty($data['approver_config'])) {
            return $data;
        }

        // Build approver_config from dot-notation keys (approver_config.user_ids etc.)
        $config = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'approver_config.')) {
                $configKey = str_replace('approver_config.', '', $key);
                $config[$configKey] = $value;
                unset($data[$key]);
            }
        }

        $data['approver_config'] = $config ?: [];

        return $data;
    }

    /**
     * Get approvable models from resources registered in the current panel.
     *
     * @return array<string, string> FQCN => human label
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-action-approvals::approval.flow_table.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('approvable_type')
                    ->label(__('filament-action-approvals::approval.flow_table.model'))
                    ->placeholder(__('filament-action-approvals::approval.flow_table.any'))
                    ->formatStateUsing(fn (?string $state): string => ApprovableModelLabel::resolve($state)),
                TextColumn::make('action_key')
                    ->label(__('filament-action-approvals::approval.flow_table.action_key'))
                    ->placeholder(__('filament-action-approvals::approval.flow.any_action'))
                    ->formatStateUsing(fn (ApprovalFlow $record): string => ApprovableActionLabel::resolve($record->approvable_type, $record->action_key))
                    ->searchable(),
                TextColumn::make('steps_count')
                    ->counts('steps')
                    ->label(__('filament-action-approvals::approval.flow_table.steps')),
                IconColumn::make('is_active')
                    ->label(__('filament-action-approvals::approval.flow_table.is_active'))
                    ->boolean(),
                DateDisplay::column(
                    TextColumn::make('created_at')
                        ->label(__('filament-action-approvals::approval.flow_table.created_at')),
                )
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalFlows::route('/'),
            'create' => CreateApprovalFlow::route('/create'),
            'edit' => EditApprovalFlow::route('/{record}/edit'),
        ];
    }
}
