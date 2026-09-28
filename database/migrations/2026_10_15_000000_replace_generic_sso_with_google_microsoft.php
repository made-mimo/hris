<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the generic, never-wired-up OIDC/LDAP SSO stub (sso_provider,
 * sso_client_id, sso_client_secret, sso_endpoint) with concrete Google
 * Workspace and Microsoft 365 configuration, one set of columns per
 * provider so both can be enabled at the same time — see App\Http\
 * Controllers\SsoController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['sso_enabled', 'sso_provider', 'sso_client_id', 'sso_client_secret', 'sso_endpoint', 'sso_domain']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('sso_google_enabled')->default(false);
            $table->string('sso_google_client_id')->nullable();
            $table->text('sso_google_client_secret')->nullable();
            $table->string('sso_google_domain')->nullable();

            $table->boolean('sso_microsoft_enabled')->default(false);
            $table->string('sso_microsoft_client_id')->nullable();
            $table->text('sso_microsoft_client_secret')->nullable();
            $table->string('sso_microsoft_tenant_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'sso_google_enabled', 'sso_google_client_id', 'sso_google_client_secret', 'sso_google_domain',
                'sso_microsoft_enabled', 'sso_microsoft_client_id', 'sso_microsoft_client_secret', 'sso_microsoft_tenant_id',
            ]);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('sso_enabled')->default(false);
            $table->string('sso_provider')->nullable();
            $table->string('sso_client_id')->nullable();
            $table->text('sso_client_secret')->nullable();
            $table->string('sso_endpoint')->nullable();
            $table->string('sso_domain')->nullable();
        });
    }
};
