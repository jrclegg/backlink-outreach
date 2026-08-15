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
        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->string('oauth_provider')->nullable()->after('is_active')->comment('gmail, outlook, or manual');
            $table->text('oauth_access_token')->nullable()->after('oauth_provider');
            $table->text('oauth_refresh_token')->nullable()->after('oauth_access_token');
            $table->timestamp('oauth_expires_at')->nullable()->after('oauth_refresh_token');
            $table->timestamp('last_verified_at')->nullable()->after('oauth_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_inboxes', function (Blueprint $table) {
            $table->dropColumn([
                'oauth_provider',
                'oauth_access_token',
                'oauth_refresh_token',
                'oauth_expires_at',
                'last_verified_at',
            ]);
        });
    }
};
