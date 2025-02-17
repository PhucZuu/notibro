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
            $table->string('title',255)->nullable();
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->boolean('is_reminder')->default(0);
            $table->json('reminder')->nullable();
            $table->boolean('is_done')->default(0);
            $table->json('user_ids')->nullable();
            $table->string('location',255)->nullable();
            $table->enum('type',['event','task','appointment'])->default('event');
            $table->boolean('is_all_day')->default(0);
            $table->boolean('is_repeat')->default(0);
            $table->boolean('is_busy')->default(0);
            $table->string('path',255)->nullable();
            
            $table->enum('date_space',['daily','weekly','monthly','yearly'])->nullable();
            $table->integer('repeat_space')->nullable();
            $table->date('end_repeat')->nullable();
            $table->json('day_of_week')->nullable()->comment('Mảng ngày trong tuần ["mo", "we", "fr"]');
            $table->json('day_of_month')->nullable()->comment('Mảng ngày trong tháng  [1,24,15]');
            $table->json('by_month')->nullable()->comment('Mảng tháng trong năm [1,12,11]');
            $table->json('exclude_time')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // $table->enum('frequency',['daily','weekly','monthly','yearly'])->nullable();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
