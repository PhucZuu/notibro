<?php

use App\Models\Color;
use App\Models\Repeatition;
use App\Models\Tag;
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
            $table->foreignIdFor(Tag::class)->nullable()->constrained()->nullOnDelete();
            // $table->foreignIdFor(Color::class)->constrained();
            // $table->foreignIdFor(Timezone::class)->constrained();
            $table->string('color_code', 255)->nullable();
            $table->string('timezone_code', 255)->nullable();

            $table->string('title',255)->nullable();
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->boolean('is_reminder')->default(0);
            $table->json('reminder')->nullable();
            $table->boolean('is_done')->default(0);
            $table->json('attendees')->nullable();
            $table->string('location',255)->nullable();
            $table->enum('type',['event','task','appointment'])->default('event');
            $table->boolean('is_all_day')->default(0);
            $table->boolean('is_repeat')->default(0);
            $table->boolean('is_busy')->default(0);
            $table->string('path',255)->nullable();
            $table->enum('freq',['daily','weekly','monthly','yearly'])->nullable()->comment('Tần suất lặp lại của sự kiện');
            $table->integer('interval')->nullable()->comment('Khoảng cách giữa các lần lặp lại');
            $table->dateTime('until')->nullable()->comment('Ngày kết thúc lặp lại');
            $table->integer('count')->nullable()->comment('Số lần lặp lại');
            $table->json('byweekday')->nullable()->comment('Mảng ngày trong tuần ["MO", "TU", "WE", "TH", "FR", "SA", "SU"]');
            $table->json('bymonthday')->nullable()->comment('Mảng ngày trong tháng  [1,24,15]');
            $table->json('bymonth')->nullable()->comment('Mảng tháng trong năm [1,12,11]');
            $table->json('bysetpos')->nullable()->comment('Xác định vị trí trong tuần hoặc tháng Xác định vị trí trong tuần hoặc tháng.');
            $table->json('exclude_time')->nullable()->comment('Mảng thời gian bị loại trừ');
            $table->integer('parent_id')->nullable();
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
