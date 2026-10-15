<?php
return ['defaults'=>['guard'=>'web'], 'guards'=>['web'=>['driver'=>'session','provider'=>'members']], 'providers'=>['members'=>['driver'=>'eloquent','model'=>App\Models\Member::class]]];
