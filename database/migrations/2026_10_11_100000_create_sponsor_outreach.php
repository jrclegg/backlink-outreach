<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('sponsor_campaigns', function (Blueprint $t) {
            $t->id(); $t->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('email_inbox_id')->nullable()->constrained()->nullOnDelete();
            $t->text('audience'); $t->text('offer'); $t->string('booking_url', 2048);
            $t->json('excluded_domains')->nullable(); $t->unsignedInteger('daily_limit')->default(10);
            $t->string('send_time')->default('09:00'); $t->boolean('enabled')->default(false);
            $t->boolean('auto_send')->default(false); $t->timestamps();
        });
        Schema::create('sponsor_batches', function (Blueprint $t) {
            $t->id(); $t->foreignId('sponsor_campaign_id')->constrained()->cascadeOnDelete();
            $t->date('day'); $t->string('status')->default('queued');
            $t->longText('research')->nullable(); $t->text('error')->nullable(); $t->timestamps();
            $t->unique(['sponsor_campaign_id', 'day']);
        });
        Schema::create('sponsor_prospects', function (Blueprint $t) {
            $t->id(); $t->foreignId('sponsor_campaign_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sponsor_batch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('outreach_message_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('organization'); $t->string('domain'); $t->string('email');
            $t->string('source_url', 2048); $t->string('product_url', 2048); $t->text('relevance');
            $t->string('status')->default('queued'); $t->text('reason')->nullable();
            $t->json('review')->nullable(); $t->timestamp('reviewed_at')->nullable();
            $t->string('signature', 64)->nullable(); $t->timestamps();
            $t->unique(['sponsor_campaign_id', 'domain']); $t->unique(['sponsor_campaign_id', 'email']);
        });
    }
    public function down(): void { foreach (['sponsor_prospects','sponsor_batches','sponsor_campaigns'] as $table) Schema::dropIfExists($table); }
};
