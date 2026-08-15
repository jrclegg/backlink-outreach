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
        Schema::create('backlink_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_recipient_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('target_page_url');
            $table->string('expected_link_url');
            $table->string('expected_anchor_text')->nullable();
            $table->string('status')->default('pending');
            $table->integer('check_count')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('found_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlink_checks');
    }
};
