<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_security', function (Blueprint $table) {
            $table->timestamp('admin_pin_configured_at')->nullable();
        });

        $configured = DB::table('members')
            ->where('role', 'admin')
            ->whereNotNull('pin_hash')
            ->exists();
        $security = DB::table('workspace_security')->where('id', 1)->first();
        $now = now();

        if (! $security) {
            DB::table('workspace_security')->insert([
                'id' => 1,
                'member_pin_hash' => null,
                'admin_pin_configured_at' => $configured ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } elseif ($configured && ! $security->admin_pin_configured_at) {
            DB::table('workspace_security')->where('id', 1)->update([
                'admin_pin_configured_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('workspace_security', function (Blueprint $table) {
            $table->dropColumn('admin_pin_configured_at');
        });
    }
};
