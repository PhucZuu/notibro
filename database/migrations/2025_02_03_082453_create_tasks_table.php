<?php

use App\Models\Color;
use App\Models\Repeatition;
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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained();
            $table->foreignIdFor(Color::class)->constrained();
            $table->foreignIdFor(Timezone::class)->constrained();
            $table->foreignIdFor(Repeatition::class)->nullable()->constrained();
            $table->string('title',255)->nullable();
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->boolean('is_reminder')->default(0);
            $table->json('reminder')->nullable();
            $table->boolean('is_done')->default(0);
            $table->json('user_ids')->nullable();
            $table->string('location',255)->nullable();
            $table->enum('type',['event','task','appointment']);
            $table->boolean('is_busy')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
