<?php

namespace CoringaWc\FilamentActionApprovals\Support;

use Filament\Forms\Components\Field;
use Filament\Support\Icons\Heroicon;

class FormFieldHint
{
    public static function apply(Field $component, string $tooltip): Field
    {
        return $component
            ->hintIcon(Heroicon::OutlinedExclamationCircle, $tooltip)
            ->hintColor('warning');
    }
}
