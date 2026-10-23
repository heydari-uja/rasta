<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('role')->default('member')->index();
            $table->string('pin_hash')->nullable();
        });

        Schema::create('workspace_security', function (Blueprint $table) {
            $table->id();
            $table->string('member_pin_hash')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_security');
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'pin_hash']);
        });
    }
};
