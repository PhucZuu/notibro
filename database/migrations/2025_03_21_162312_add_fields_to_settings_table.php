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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('hour_format')->default('24h')->comment('Định dạng giờ hiển thị');  
            $table->string('display_type', 50)->default('dayGridMonth')->comment('Chế độ hiển thị');  
            $table->boolean('is_display_dayoff')->default(0)->comment('Có hiển thị các ngày lễ không');  
            $table->json('tittle_format_options')->comment('Chế độ hiển thị tiêu đề lịch');  
            $table->json('column_header_format_option')->comment('Chế độ hiển thị tiêu đề lịch');  
            $table->string('first')->default('1')->comment('Chế độ hiển thị tiêu đề lịch');  
            $table->enum('notification_type', ['off', 'desktop', 'alerts', 'both'])->default('desktop')->comment('Cài đặt thông báo');  
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('hour_format');  
            $table->dropColumn('display_type');  
            $table->dropColumn('is_display_dayoff');  
            $table->dropColumn('tittle_format_options');  
            $table->dropColumn('column_header_format_option');  
            $table->dropColumn('first');  
            $table->dropColumn('notification_type');  
        });
    }
};
