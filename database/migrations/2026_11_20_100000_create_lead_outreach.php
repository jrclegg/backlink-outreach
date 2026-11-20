<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Automated lead campaigns for a product (e.g. Invoice Customiser): research small businesses, draft, quality-check, send.
return new class extends Migration {
    public function up(): void {
        Schema::create('lead_campaigns', function (Blueprint $t) {
            $t->id(); $t->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('email_inbox_id')->nullable()->constrained()->nullOnDelete();
            $t->text('audience'); $t->text('offer'); $t->string('product_url', 2048); $t->text('sender'); $t->longText('examples')->nullable();
            $t->json('focuses')->nullable(); $t->json('excluded_domains')->nullable(); $t->unsignedInteger('daily_limit')->default(10);
            $t->string('send_time')->default('10:00'); $t->boolean('enabled')->default(false);
            $t->boolean('auto_send')->default(false); $t->timestamps();
        });
        Schema::create('lead_batches', function (Blueprint $t) {
            $t->id(); $t->foreignId('lead_campaign_id')->constrained()->cascadeOnDelete();
            $t->date('day'); $t->string('status')->default('queued');
            $t->longText('research')->nullable(); $t->text('error')->nullable(); $t->timestamps();
            $t->unique(['lead_campaign_id', 'day']);
        });
        Schema::create('lead_prospects', function (Blueprint $t) {
            $t->id(); $t->foreignId('lead_campaign_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lead_batch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('outreach_message_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('organization'); $t->string('domain'); $t->string('contact_name')->nullable(); $t->string('role')->nullable();
            $t->string('email'); $t->string('source_url', 2048); $t->string('detail_url', 2048); $t->text('detail');
            $t->string('status')->default('queued'); $t->text('reason')->nullable();
            $t->json('review')->nullable(); $t->timestamp('reviewed_at')->nullable();
            $t->longText('ai_draft')->nullable(); $t->string('signature', 64)->nullable(); $t->timestamps();
            $t->unique(['lead_campaign_id', 'domain']); $t->unique(['lead_campaign_id', 'email']);
        });
    }
    public function down(): void { foreach (['lead_prospects','lead_batches','lead_campaigns'] as $table) Schema::dropIfExists($table); }
};
