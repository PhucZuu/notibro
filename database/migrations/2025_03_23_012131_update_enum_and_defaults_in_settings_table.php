<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            // Xóa hai cột `date_format` và `time_format`
            $table->dropColumn(['date_format', 'time_format']);
        });

        Schema::table('settings', function (Blueprint $table) {
            // Thêm lại hai cột với enum mới
            $table->enum('date_format', ['YYYY-MM-DD', 'MM-DD-YYYY', 'DD-MM-YYYY', 'DD/MM/YYYY', 'MM/DD/YYYY'])
                ->default('YYYY-MM-DD');

            $table->enum('time_format', ['h:mm A', 'HH:mm'])
                ->default('HH:mm');
        });
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            // Xóa hai cột vừa thêm
            $table->dropColumn(['date_format', 'time_format']);
        });

        Schema::table('settings', function (Blueprint $table) {
            // Thêm lại hai cột với enum cũ
            $table->enum('date_format', ['d/m/Y', 'm/d/Y', 'Y-m-d'])
                ->default('d/m/Y');

            $table->enum('time_format', ['h:mm A', 'HH:mm'])
                ->default('HH:mm');
        });
    }
};
