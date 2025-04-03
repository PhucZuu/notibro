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
        Schema::table('tasks', function (Blueprint $table) {  
            $table->string('default_permission', 100)->nullable()->default('viewer'); // Thêm trường default_permission  
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {  
            $table->dropColumn('default_permission'); // Xóa trường default_permission khi rollback  
        }); 
    }
};
