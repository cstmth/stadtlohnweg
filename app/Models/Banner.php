<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    public const COLORS = [
        'neutral' => 'banner_color_neutral',
        'info' => 'banner_color_info',
        'warning' => 'banner_color_warning',
    ];

    protected $fillable = [
        'enabled',
        'color',
        'text_de',
        'text_en',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /**
     * Es gibt genau eine Banner-Konfiguration (Singleton-Zeile).
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'enabled' => false,
            'color' => 'neutral',
        ]);
    }

    public function textFor(string $locale): ?string
    {
        return match ($locale) {
            'de' => $this->text_de,
            default => $this->text_en,
        };
    }
}
