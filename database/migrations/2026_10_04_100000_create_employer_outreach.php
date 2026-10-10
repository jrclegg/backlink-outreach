<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_campaigns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('email_inbox_id')->nullable()->constrained()->nullOnDelete();
            $t->text('audience');
            $t->text('offer');
            $t->string('posting_url', 2048);
            $t->unsignedInteger('weekly_limit')->default(10);
            $t->boolean('enabled')->default(false);
            $t->boolean('auto_send')->default(false);
            $t->boolean('discover')->default(true);
            $t->unsignedTinyInteger('weekday')->default(1);
            $t->string('send_time')->default('09:00');
            $t->timestamps();
        });
        Schema::create('employer_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employer_campaign_id')->constrained()->cascadeOnDelete();
            $t->string('employer');
            $t->string('email');
            $t->string('source_url', 2048);
            $t->text('relevance');
            $t->string('status')->default('suggested');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->unique(['employer_campaign_id', 'email']);
        });
        Schema::create('employer_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employer_campaign_id')->constrained()->cascadeOnDelete();
            $t->string('week');
            $t->string('status')->default('queued');
            $t->text('error')->nullable();
            $t->longText('research')->nullable();
            $t->json('sources')->nullable();
            $t->timestamps();
            $t->unique(['employer_campaign_id', 'week']);
        });
        Schema::create('employer_batch_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employer_batch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employer_contact_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('outreach_message_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status')->default('queued');
            $t->text('error')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['employer_batch_items', 'employer_batches', 'employer_contacts', 'employer_campaigns'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
