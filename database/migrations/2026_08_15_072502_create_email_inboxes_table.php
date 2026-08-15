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
        Schema::create('email_inboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email_address');
            $table->string('from_name');
            $table->string('smtp_host');
            $table->integer('smtp_port');
            $table->string('smtp_username');
            $table->text('smtp_password');
            $table->string('smtp_encryption')->default('tls');
            $table->string('imap_host');
            $table->integer('imap_port');
            $table->string('imap_username');
            $table->text('imap_password');
            $table->string('imap_encryption')->default('tls');
            $table->string('imap_folder')->default('INBOX');
            $table->integer('max_sends_per_hour')->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_inboxes');
    }
};
