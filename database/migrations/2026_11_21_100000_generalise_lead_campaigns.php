<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// Lead campaigns become general AI outreach campaigns that are set up in the UI: any number per project,
// a goal, and the campaign-specific fit and email rules that used to be written into the prompts.
return new class extends Migration {
    // The Invoice Customiser rules previously hard-coded in ResearchLeadBatch and DraftLeadEmail.
    const INVOICE_FIT = 'Choose businesses that clearly bill clients by invoice for project or ongoing work, are small enough that the owner does the invoicing, and show no sign of already invoicing from accounting software. Skip businesses that mainly take payment upfront online (shops, booking systems) and anything the offer says the product does not support.';
    const INVOICE_EMAIL = 'Mention the price and the no-signup sample, accurate to the offer but in your own casual words. Never imply it sends invoices, chases payments or does bookkeeping. Do not claim to know how this business invoices today. The subject can be specific to them or to invoices.';

    public function up(): void {
        Schema::table('lead_campaigns', function (Blueprint $t) {
            $t->string('name')->default('Outreach');
            $t->string('goal')->default('sales');
            $t->longText('brief')->nullable();
            $t->text('fit_rules')->nullable();
            $t->text('email_rules')->nullable();
            $t->unsignedSmallInteger('min_words')->default(70);
            $t->unsignedSmallInteger('max_words')->default(130);
        });
        Schema::table('lead_campaigns', function (Blueprint $t) {
            $t->index('project_id');
            $t->dropUnique(['project_id']);
        });
        Schema::table('projects', function (Blueprint $t) {
            $t->string('client_name')->nullable();
        });
        DB::table('lead_campaigns')->whereNull('fit_rules')->update(['name' => 'Small businesses', 'fit_rules' => self::INVOICE_FIT, 'email_rules' => self::INVOICE_EMAIL]);
    }

    public function down(): void {
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('client_name'));
        Schema::table('lead_campaigns', function (Blueprint $t) {
            $t->unique('project_id');
            $t->dropIndex(['project_id']);
        });
        Schema::table('lead_campaigns', fn (Blueprint $t) => $t->dropColumn(['name', 'goal', 'brief', 'fit_rules', 'email_rules', 'min_words', 'max_words']));
    }
};
