<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $block
 * @property string $appliance
 * @property Carbon $reserved_date
 * @property int $hour
 * @property string $room_number
 * @property int|null $user_id
 * @property string|null $pin
 */
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    /**
     * Reservierungen werden aus Datenschutzgründen nach diesem Zeitraum gelöscht.
     */
    public const RETENTION_DAYS = 14;

    /**
     * Wie weit im Voraus reserviert werden darf.
     */
    public const MAX_ADVANCE_MONTHS = 1;

    /**
     * Früheste Startstunde (07:00) und späteste Startstunde (21:00–22:00).
     */
    public const FIRST_HOUR = 7;

    public const LAST_HOUR = 21;

    /**
     * Die beiden Waschkeller / Blöcke.
     *
     * @var array<string, string>
     */
    public const BLOCKS = [
        'A' => 'A-Block',
        'C' => 'C-Block',
    ];

    /**
     * Die drei Geräte je Keller. Werte sind Übersetzungs-Keys (siehe lang/de.json).
     *
     * @var array<string, string>
     */
    public const APPLIANCES = [
        'left' => 'appliance_left',
        'right' => 'appliance_right',
        'dryer' => 'appliance_dryer',
    ];

    /**
     * Zimmernummer-Schema aBBBcc: Buchstabe A–D, drei Ziffern, optional ".Ziffer".
     */
    public const ROOM_REGEX = '/^[A-D][0-9]{3}(\.[0-9])?$/';

    protected $fillable = [
        'block',
        'appliance',
        'reserved_date',
        'hour',
        'room_number',
        'user_id',
        'pin',
    ];

    protected $hidden = [
        'pin',
    ];

    protected function casts(): array
    {
        return [
            'reserved_date' => 'date',
            'hour' => 'integer',
            'pin' => 'hashed',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alle gültigen Startstunden (7..21).
     *
     * @return array<int, int>
     */
    public static function hours(): array
    {
        return range(self::FIRST_HOUR, self::LAST_HOUR);
    }

    /**
     * Normalisiert eine Zimmernummer (Großbuchstabe, ohne Leerzeichen).
     */
    public static function normalizeRoom(string $room): string
    {
        return strtoupper(trim($room));
    }

    /**
     * Liegt der Slot in der Vergangenheit (bereits abgelaufen)?
     */
    public function hasPassed(): bool
    {
        return $this->endsAt()->isPast();
    }

    /**
     * Endzeitpunkt des Slots (Stunde + 1).
     */
    public function endsAt(): CarbonInterface
    {
        return $this->reserved_date->copy()->setTime($this->hour + 1, 0);
    }

    /**
     * Scope: nur Reservierungen, die noch nicht abgelaufen sind
     * (heutiger Tag bleibt vollständig sichtbar).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('reserved_date', '>=', Carbon::today());
    }

    /**
     * Scope: innerhalb der Aufbewahrungsfrist (für "Meine Reservierungen").
     */
    public function scopeWithinRetention(Builder $query): Builder
    {
        return $query->whereDate('reserved_date', '>=', Carbon::today()->subDays(self::RETENTION_DAYS));
    }
}
