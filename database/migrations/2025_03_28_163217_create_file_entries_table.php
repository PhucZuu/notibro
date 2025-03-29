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
        Schema::create('file_entries', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('client_name');
            $table->string('extension', 10);
            $table->unsignedBigInteger('size');
            $table->string('mime');
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->unsignedBigInteger('owner_id');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_entries');
    }
};
