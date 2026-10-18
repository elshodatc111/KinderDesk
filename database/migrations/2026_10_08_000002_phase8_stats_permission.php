<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mavjud lavozimlarga yangi «Statistika» ruxsati (direktor har doim ko'radi)
        foreach (DB::table('roles')->whereIn('key', ['admin', 'accountant'])->get() as $r) {
            $perms = array_values(array_unique(array_merge(json_decode($r->permissions, true) ?: [], ['stats.view'])));
            DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode($perms)]);
        }
    }

    public function down(): void
    {
    }
};
