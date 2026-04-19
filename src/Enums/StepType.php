<?php

declare(strict_types=1);

namespace CoringaWc\FilamentActionApprovals\Enums;

use Filament\Support\Contracts\HasLabel;

enum StepType: string implements HasLabel
{
    case Single = 'single';
    case Sequential = 'sequential';
    case Parallel = 'parallel';

    public function getLabel(): string
    {
        return __('filament-action-approvals::approval.step_type.'.$this->value);
    }
}
