<?php

namespace App\Models;

use Database\Factories\DesignTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignTemplate extends Model
{
    /** @use HasFactory<DesignTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'based_on',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * The template slug a book's export settings use to reference this template.
     */
    public function slug(): string
    {
        return 'custom:'.$this->id;
    }
}
