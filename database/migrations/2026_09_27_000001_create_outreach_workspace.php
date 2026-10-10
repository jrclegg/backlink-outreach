<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $t) {
            $t->string('ai_model')->default('claude-sonnet-4-6');
        });
        Schema::table('email_inboxes', function (Blueprint $t) {
            $t->foreignId('default_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $t->string('provider')->default('custom');
            $t->text('sync_error')->nullable();
        });
        Schema::create('target_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->string('url', 2048);
            $t->string('title');
            $t->text('brief')->nullable();
            $t->timestamps();
        });
        Schema::create('research_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('target_page_id')->constrained()->cascadeOnDelete();
            $t->text('instructions')->nullable();
            $t->string('status')->default('queued');
            $t->longText('report')->nullable();
            $t->json('sources')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
        });
        Schema::create('opportunities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('target_page_id')->constrained()->cascadeOnDelete();
            $t->foreignId('research_run_id')->nullable()->constrained()->nullOnDelete();
            $t->string('url', 2048);
            $t->string('title');
            $t->string('type')->default('resource');
            $t->text('relevance');
            $t->text('attainability');
            $t->text('angle');
            $t->json('sources');
            $t->string('contact_email')->nullable();
            $t->string('contact_url', 2048)->nullable();
            $t->string('status')->default('new');
            $t->text('notes')->nullable();
            $t->string('placement_url', 2048)->nullable();
            $t->string('link_status')->default('unchecked');
            $t->timestamp('checked_at')->nullable();
            $t->timestamp('next_check_at')->nullable();
            $t->timestamps();
        });
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('email_inbox_id')->constrained()->cascadeOnDelete();
            $t->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $t->string('contact_email');
            $t->string('subject');
            $t->string('status')->default('open');
            $t->timestamp('last_message_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('outreach_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('email_inbox_id')->constrained()->cascadeOnDelete();
            $t->string('direction');
            $t->string('status')->default('draft');
            $t->string('from_email');
            $t->string('to_email');
            $t->string('subject');
            $t->longText('body');
            $t->string('message_id');
            $t->text('in_reply_to')->nullable();
            $t->text('references')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
            $t->unique(['email_inbox_id', 'message_id']);
        });
    }

    public function down(): void
    {
        foreach (['outreach_messages', 'conversations', 'opportunities', 'research_runs', 'target_pages'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('email_inboxes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('default_project_id');
            $t->dropColumn(['provider', 'sync_error']);
        });
        Schema::table('organizations', fn (Blueprint $t) => $t->dropColumn('ai_model'));
    }
};
