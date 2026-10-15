<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('members', function(Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->string('job_title')->nullable(); $t->string('color')->default('#e5ddf7'); $t->boolean('active')->default(true); $t->rememberToken(); $t->timestamps(); }); }
 public function down(): void { Schema::dropIfExists('members'); }
};
