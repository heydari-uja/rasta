<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('member_stage_id');
            $table->index(['member_stage_id', 'position']);
        });

        $stageIds = DB::table('tasks')->whereNotNull('member_stage_id')->distinct()->pluck('member_stage_id');
        foreach ($stageIds as $stageId) {
            $ids = DB::table('tasks')
                ->where('member_stage_id', $stageId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id');
            foreach ($ids as $position => $id) {
                DB::table('tasks')->where('id', $id)->update(['position' => $position]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['member_stage_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
