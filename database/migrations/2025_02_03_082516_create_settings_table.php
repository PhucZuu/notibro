<?php

use App\Models\Timezone;
use App\Models\User;
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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained();
            $table->foreignIdFor(Timezone::class)->nullable()->constrained();
            $table->enum('language', ['en','vi'])->default('en');
            $table->enum('theme', ['light', 'dark'])->default('light');
            $table->enum('date_format',['d/m/Y','m/d/Y','Y-m-d'])->default('d/m/Y');
            $table->enum('time_format', ['h:mmA','HH:mm'])->default('h:mmA');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
