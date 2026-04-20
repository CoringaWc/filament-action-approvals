<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\ApprovalFlow;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Pages\CreateApprovalFlow;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Pages\EditApprovalFlow;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Pages\ListApprovalFlows;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Schemas\ApprovalFlowForm;
use CoringaWc\FilamentActionApprovals\Resources\ApprovalFlows\Tables\ApprovalFlowsTable;
use Filament\Clusters\Cluster;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApprovalFlowResource extends Resource
{
    protected static ?string $model = ApprovalFlow::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return FilamentActionApprovalsPlugin::resolveResourceNavigationIcon()
            ?? static::$navigationIcon;
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentActionApprovalsPlugin::resolveResourceNavigationSort();
    }

    /**
     * @return class-string<Cluster>|null
     */
    public static function getCluster(): ?string
    {
        return FilamentActionApprovalsPlugin::resolveResourceCluster();
    }

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
        return ApprovalFlowForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApprovalFlowsTable::configure($table);
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
