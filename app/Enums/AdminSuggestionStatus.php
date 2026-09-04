<?php

namespace App\Enums;

enum AdminSuggestionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('admin_suggestions.status_'.$this->value);
    }
}
