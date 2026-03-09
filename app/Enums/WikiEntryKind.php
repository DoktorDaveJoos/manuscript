<?php

namespace App\Enums;

enum WikiEntryKind: string
{
    case Location = 'location';
    case Organization = 'organization';
    case Item = 'item';
    case Lore = 'lore';
}
