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
        Schema::table('task_group_messages', function (Blueprint $table) {
            $table->foreignId('reply_to')->nullable()->constrained('task_group_messages')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_group_messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to']);
            $table->dropColumn('reply_to');
        });
    }
};
