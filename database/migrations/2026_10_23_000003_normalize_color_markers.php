<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['projects', 'labels'] as $table) {
            DB::table($table)->orderBy('id')->get(['id'])->each(function ($record) use ($table): void {
                $marker = 'project-marker-'.(((int) $record->id - 1) % 8 + 1);
                DB::table($table)->where('id', $record->id)->update(['color' => $marker]);
            });
        }

        DB::table('members')->update(['color' => 'project-marker-4']);
    }

    public function down(): void
    {
        // The previous custom colors cannot be reconstructed after normalization.
    }
};
