<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Resources\Approvals;

use CoringaWc\FilamentActionApprovals\FilamentActionApprovalsPlugin;
use CoringaWc\FilamentActionApprovals\Models\Approval;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Pages\ListApprovals;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Schemas\ApprovalInfolist;
use CoringaWc\FilamentActionApprovals\Resources\Approvals\Tables\ApprovalsTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApprovalResource extends Resource
{
    protected static ?string $model = Approval::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return FilamentActionApprovalsPlugin::resolveApprovalResourceNavigationIcon()
            ?? static::$navigationIcon;
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentActionApprovalsPlugin::resolveApprovalResourceNavigationSort();
    }

    public static function getModelLabel(): string
    {
        return __('filament-action-approvals::approval.approval_resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-action-approvals::approval.approval_resource_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentActionApprovalsPlugin::resolveNavigationGroup();
    }

    public static function infolist(Schema $schema): Schema
    {
        return ApprovalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApprovalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovals::route('/'),
        ];
    }
}
