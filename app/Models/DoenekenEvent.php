<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoenekenEvent extends Model
{
    protected $fillable = [
        'date',
        'closed',
        'custom_text',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'closed' => 'boolean',
        ];
    }
}
