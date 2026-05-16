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
        Schema::table('applications', function (Blueprint $table) {
            // Slug chosen by developer, e.g. "myapp" → myapp.nolbase.app
            $table->string('nolbase_subdomain')->nullable()->unique()->after('fqdn');
            // Cloudflare DNS record ID — needed to delete the record on cleanup
            $table->string('nolbase_dns_record_id')->nullable()->after('nolbase_subdomain');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['nolbase_subdomain', 'nolbase_dns_record_id']);
        });
    }
};
