<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Writes text beginning with "=" as plain text instead of a formula. Every spreadsheet this app
 * produces (exports, import templates) carries names people typed in — a church or person
 * named "=HYPERLINK(...)" would otherwise turn into a live formula in whoever opens the file.
 * The app never writes formulas on purpose. Registered globally in AppServiceProvider.
 */
class TextOnlyValueBinder extends DefaultValueBinder
{
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && str_starts_with($value, '=')) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
