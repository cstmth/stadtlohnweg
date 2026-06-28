<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doeneken_events', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->boolean('closed')->default(false);
            $table->string('custom_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doeneken_events');
    }
};
