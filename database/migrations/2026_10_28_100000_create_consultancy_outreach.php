<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('consultancy_campaigns', function (Blueprint $t) {
            $t->id(); $t->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('email_inbox_id')->nullable()->constrained()->nullOnDelete();
            $t->text('audience'); $t->text('sender'); $t->longText('examples');
            $t->json('excluded_domains')->nullable(); $t->unsignedInteger('daily_limit')->default(3);
            $t->string('send_time')->default('09:30'); $t->boolean('enabled')->default(false);
            $t->boolean('auto_send')->default(false); $t->timestamps();
        });
        Schema::create('consultancy_batches', function (Blueprint $t) {
            $t->id(); $t->foreignId('consultancy_campaign_id')->constrained()->cascadeOnDelete();
            $t->date('day'); $t->string('status')->default('queued');
            $t->longText('research')->nullable(); $t->text('error')->nullable(); $t->timestamps();
            $t->unique(['consultancy_campaign_id', 'day']);
        });
        Schema::create('consultancy_prospects', function (Blueprint $t) {
            $t->id(); $t->foreignId('consultancy_campaign_id')->constrained()->cascadeOnDelete();
            $t->foreignId('consultancy_batch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('outreach_message_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('organization'); $t->string('domain'); $t->string('contact_name'); $t->string('role')->nullable();
            $t->string('email'); $t->string('source_url', 2048); $t->string('detail_url', 2048); $t->text('detail');
            $t->string('status')->default('queued'); $t->text('reason')->nullable();
            $t->json('review')->nullable(); $t->timestamp('reviewed_at')->nullable();
            $t->longText('ai_draft')->nullable(); $t->string('signature', 64)->nullable(); $t->timestamps();
            $t->unique(['consultancy_campaign_id', 'domain']); $t->unique(['consultancy_campaign_id', 'email']);
        });
    }
    public function down(): void { foreach (['consultancy_prospects','consultancy_batches','consultancy_campaigns'] as $table) Schema::dropIfExists($table); }
};
