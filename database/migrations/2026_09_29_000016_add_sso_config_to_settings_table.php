<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec A3: SSO (LDAP/OIDC) configuration — buildable in parallel with Phase 1 per spec 7.2. Config layer only: no real identity provider exists to test protocol wiring against in this local prototype (same "real third-party credentials... at live deployment" decision already made for S3/transactional email, PLAN.md Section 8), so the login page's SSO button stays a stub until real IdP details are entered here. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('sso_enabled')->default(false);
            $table->string('sso_provider')->nullable();
            $table->string('sso_client_id')->nullable();
            $table->text('sso_client_secret')->nullable();
            $table->string('sso_endpoint')->nullable();
            $table->string('sso_domain')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['sso_enabled', 'sso_provider', 'sso_client_id', 'sso_client_secret', 'sso_endpoint', 'sso_domain']);
        });
    }
};
