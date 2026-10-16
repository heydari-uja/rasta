<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_stages', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
            $table->unsignedBigInteger('product_stage_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('member_stages', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
            $table->unsignedBigInteger('product_stage_id')->nullable(false)->change();
        });
    }
};
