<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = ['tasks' => ['stage_id' => 'product_stage_id', 'assigned_to' => 'owner_id'], 'goals' => ['success_criteria' => 'success_criterion', 'end_date' => 'target_date'], 'product_stages' => ['title' => 'name'], 'member_stages' => ['title' => 'name'], 'labels' => ['created_by' => 'member_id'], 'attachments' => ['mime_type' => 'mime']];

    public function up(): void
    {
        foreach ($this->columns as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $old => $new) {
                    $t->renameColumn($old, $new);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->columns) as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $old => $new) {
                    $t->renameColumn($new, $old);
                }
            });
        }
    }
};
