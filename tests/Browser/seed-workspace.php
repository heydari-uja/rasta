<?php

use App\Models\Member;
use App\Models\WorkspaceSecurity;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$memberPin = getenv('RASTA_TEST_MEMBER_PIN') ?: '2468';
$adminPin = getenv('RASTA_TEST_ADMIN_PIN') ?: '8642';

WorkspaceSecurity::singleton()->forceFill([
    'member_pin_hash' => Hash::make($memberPin),
    'admin_pin_configured_at' => now(),
])->save();

(new Member)->forceFill([
    'name' => 'مدیر آزمون',
    'job_title' => 'مدیر',
    'role' => 'admin',
    'active' => true,
    'pin_hash' => Hash::make($adminPin),
])->save();
