<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('task_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên nhóm
            $table->foreignId('task_id')->constrained()->onDelete('cascade'); // Liên kết với task
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Người tạo nhóm
            $table->timestamps();
        });
        
    }

    public function down()
    {
        Schema::dropIfExists('task_groups');
    }
};
