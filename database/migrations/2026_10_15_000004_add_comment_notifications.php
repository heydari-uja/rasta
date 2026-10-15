<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('excerpt', 240)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['member_id', 'read_at']);
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('comment_id')->nullable()->after('task_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->date('due_date')->nullable()->change();
        });

        DB::table('tasks')->update(['owner_id' => DB::raw('created_by')]);
    }

    public function down(): void
    {
        DB::table('tasks')->whereNull('start_date')->update(['start_date' => now()->toDateString()]);
        DB::table('tasks')->whereNull('due_date')->update(['due_date' => DB::raw('start_date')]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
            $table->date('due_date')->nullable(false)->change();
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comment_id');
        });

        Schema::dropIfExists('notifications');
    }
};
