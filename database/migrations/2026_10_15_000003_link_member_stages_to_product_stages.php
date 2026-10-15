<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_stages', function (Blueprint $table) {
            $table->foreignId('product_stage_id')->nullable()->after('member_id')->constrained('product_stages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('member_stages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_stage_id');
        });
    }
};
