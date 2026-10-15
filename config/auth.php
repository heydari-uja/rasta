<?php

use App\Models\Member;

return ['defaults' => ['guard' => 'web'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'members']], 'providers' => ['members' => ['driver' => 'eloquent', 'model' => Member::class]]];
