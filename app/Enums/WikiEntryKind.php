<?php

namespace App\Enums;

enum WikiEntryKind: string
{
    case Location = 'location';
    case Organization = 'organization';
    case Item = 'item';
    case Lore = 'lore';

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Location => 'Locations',
            self::Organization => 'Organizations',
            self::Item => 'Items',
            self::Lore => 'Lore',
        };
    }
}
