<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_batch_items', function (Blueprint $table) {
            $table->timestamp('auto_reviewed_at')->nullable();
            $table->string('auto_review_status')->nullable();
            $table->text('auto_review_reason')->nullable();
            $table->string('auto_review_signature', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employer_batch_items', fn (Blueprint $table) => $table->dropColumn(['auto_reviewed_at', 'auto_review_status', 'auto_review_reason', 'auto_review_signature']));
    }
};
