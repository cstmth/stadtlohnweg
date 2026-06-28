<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefectiveAppliance extends Model
{
    protected $fillable = [
        'block',
        'appliance',
    ];

    public static function isDefective(string $block, string $appliance): bool
    {
        return static::where('block', $block)->where('appliance', $appliance)->exists();
    }
}
