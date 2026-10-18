<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->boolean('lifetime')->default(false)->after('status');
            $t->string('manual_readonly_reason', 300)->nullable()->after('lifetime');
            $t->dateTime('manual_readonly_until')->nullable()->after('manual_readonly_reason');
            $t->dateTime('manual_readonly_at')->nullable()->after('manual_readonly_until');
            $t->unsignedBigInteger('manual_readonly_by')->nullable()->after('manual_readonly_at');
            $t->boolean('created_by_admin')->default(false)->after('manual_readonly_by');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->dropColumn(['lifetime', 'manual_readonly_reason', 'manual_readonly_until', 'manual_readonly_at', 'manual_readonly_by', 'created_by_admin']);
        });
    }
};
