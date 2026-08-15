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
        Schema::create('email_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_recipient_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('email_inbox_id')->constrained()->cascadeOnDelete();
            $table->string('from_address');
            $table->string('subject');
            $table->longText('body_text');
            $table->string('raw_message_id');
            $table->string('in_reply_to')->nullable();
            $table->string('intent')->default('unclassified');
            $table->text('ai_suggested_response')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_replies');
    }
};
