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
        Schema::create('repeatitions', function (Blueprint $table) {
            $table->id();
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->integer('interval')->default(1);
            $table->json('day_of_week')->nullable(); // ["Monday", "Thursday"]
            $table->tinyInteger('week_of_month')->nullable();
            $table->tinyInteger('month_of_year')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('end_occurrences')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repeatitions');
    }
};
