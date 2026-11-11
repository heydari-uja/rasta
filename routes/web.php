<?php

use Activities\Auth\Auth;
use Activities\Security\Security;
use Activities\Workspace\Workspace;

uri('', Auth::class, 'home', 'GET');
uri('login', Auth::class, 'loginForm', 'GET');
uri('login', Auth::class, 'login', 'POST');
uri('logout', Auth::class, 'logout', 'POST');
uri('security/setup', Auth::class, 'securitySetupForm', 'GET');
uri('api/security/setup', Security::class, 'completeInitialSetup', 'POST');

uri('profiles', Auth::class, 'profiles', 'GET');
uri('profiles', Auth::class, 'select', 'POST');
uri('profile/switch', Auth::class, 'switchProfile', 'POST');

uri('avatars/{member}', Workspace::class, 'avatar', 'GET');
uri('attachments/{attachment}', Workspace::class, 'download', 'GET');

foreach (['tasks', 'tasks/{task}', 'projects', 'projects/{project}', 'projects/{project}/goal', 'projects/{project}/stages', 'labels', 'settings'] as $path) {
    uri($path, Workspace::class, 'shell', 'GET');
}

uri('api/workspace', Workspace::class, 'data', 'GET');
uri('api/projects', Workspace::class, 'project', 'POST');
uri('api/projects/{project}', Workspace::class, 'projectRecord', 'PUT');
uri('api/projects/{project}', Workspace::class, 'projectRecord', 'DELETE');
uri('api/projects/{project}/goal', Workspace::class, 'goal', 'PUT');
uri('api/projects/{project}/stages', Workspace::class, 'stage', 'POST');
uri('api/projects/{project}/stages/{kind}/order', Workspace::class, 'order', 'PUT');
uri('api/projects/{project}/stages/{kind}/{id}', Workspace::class, 'updateStage', 'PUT');
uri('api/projects/{project}/stages/{kind}/{id}', Workspace::class, 'updateStage', 'DELETE');
uri('api/member-stages/order', Workspace::class, 'memberStageOrder', 'PUT');
uri('api/member-stages', Workspace::class, 'personalStage', 'POST');
uri('api/member-stages/{stage}', Workspace::class, 'personalStage', 'PUT');
uri('api/member-stages/{stage}', Workspace::class, 'personalStage', 'DELETE');
uri('api/tasks/{task}', Workspace::class, 'taskDetail', 'GET');
uri('api/tasks', Workspace::class, 'saveTask', 'POST');
uri('api/tasks/{task}', Workspace::class, 'saveTask', 'PUT');
uri('api/tasks/reorder', Workspace::class, 'reorderTasks', 'PUT');
uri('api/tasks/{task}/complete', Workspace::class, 'complete', 'PATCH');
uri('api/tasks/{task}/product-stage', Workspace::class, 'moveTaskStage', 'PATCH');
uri('api/tasks/{task}', Workspace::class, 'removeTask', 'DELETE');
uri('api/labels', Workspace::class, 'label', 'POST');
uri('api/labels/{label}', Workspace::class, 'label', 'PUT');
uri('api/labels/{label}', Workspace::class, 'label', 'DELETE');
uri('api/tasks/{task}/comments', Workspace::class, 'comment', 'POST');
uri('api/comments/{comment}', Workspace::class, 'editComment', 'POST');
uri('api/comments/{comment}', Workspace::class, 'editComment', 'PUT');
uri('api/comments/{comment}', Workspace::class, 'editComment', 'DELETE');
uri('api/tasks/{task}/attachments', Workspace::class, 'upload', 'POST');
uri('api/attachments/{attachment}', Workspace::class, 'removeAttachment', 'DELETE');
uri('api/notifications/read', Workspace::class, 'notifications', 'POST');
uri('api/notifications/{notification}/read', Workspace::class, 'notifications', 'POST');
uri('api/settings', Workspace::class, 'settings', 'PUT');
uri('api/settings/avatar', Workspace::class, 'uploadAvatar', 'POST');
uri('api/settings/avatar', Workspace::class, 'removeAvatar', 'DELETE');
uri('api/security/admin-pin', Security::class, 'updateAdminPin', 'PUT');
uri('api/security/member-pin', Security::class, 'updateMemberPin', 'PUT');

http_response_code(404);
echo '404 - not found';
