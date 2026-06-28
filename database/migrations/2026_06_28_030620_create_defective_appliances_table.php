<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defective_appliances', function (Blueprint $table) {
            $table->id();
            $table->string('block');
            $table->string('appliance');
            $table->timestamps();

            $table->unique(['block', 'appliance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defective_appliances');
    }
};
