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
        Schema::create('task_group_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('task_groups')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->string('file')->nullable(); // Nếu có file đính kèm
            $table->timestamps();
        });
        
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
};
