<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // Waschkeller / Block: 'A' oder 'C'.
            $table->string('block', 1);

            // Gerät: 'left' (Maschine links), 'right' (Maschine rechts), 'dryer' (Trockner).
            $table->string('appliance', 8);

            // Datum und Startstunde (7..21) des einstündigen Slots.
            $table->date('reserved_date');
            $table->unsignedTinyInteger('hour');

            // Öffentlich sichtbare Zimmernummer (aBBBcc), z. B. "A115" oder "A115.2".
            $table->string('room_number');

            // Optionaler Kontoinhaber. Gastbuchungen haben user_id = null + PIN.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Gehashte 4-stellige PIN für Gastbuchungen (zum Stornieren).
            $table->string('pin')->nullable();

            $table->timestamps();

            // Ein Slot kann nicht doppelt belegt werden.
            $table->unique(['block', 'appliance', 'reserved_date', 'hour']);

            // Schneller Zugriff auf die Wochenansicht und die Aufräum-Logik.
            $table->index(['block', 'reserved_date']);
            $table->index('reserved_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
