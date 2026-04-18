<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Support;

use Filament\Forms\Components\Select;

class TranslatableSelect
{
    public static function apply(Select $select): Select
    {
        return $select
            ->searchPrompt(__('filament-action-approvals::approval.select.search_prompt'))
            ->noOptionsMessage(__('filament-action-approvals::approval.select.no_options'))
            ->noSearchResultsMessage(__('filament-action-approvals::approval.select.no_search_results'))
            ->loadingMessage(__('filament-action-approvals::approval.select.loading'));
    }
}
