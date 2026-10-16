<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\SharedAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/tasks'));
Route::view('/login', 'auth')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::middleware(SharedAccess::class)->group(function () {
    Route::get('/profiles', [AuthController::class, 'profiles']);
    Route::post('/profiles', [AuthController::class, 'select'])->middleware('throttle:20,1');
    Route::post('/profile/switch', [AuthController::class, 'switch']);
    Route::get('/avatars/{member}', [WorkspaceController::class, 'avatar'])->name('avatar');
});
Route::middleware([SharedAccess::class, 'auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::redirect('/projects', '/tasks');
    foreach (['/projects/{project}', '/projects/{project}/goal', '/projects/{project}/stages', '/tasks', '/tasks/{task}', '/labels', '/settings'] as $path) {
        Route::get($path, function () {
            return view('workspace');
        });
    }
    Route::get('/attachments/{attachment}', [WorkspaceController::class, 'download']);
    Route::prefix('api')->middleware('throttle:120,1')->controller(WorkspaceController::class)->group(function () {
        Route::get('/workspace', 'data');
        Route::post('/projects', 'project');
        Route::match(['put', 'delete'], '/projects/{project}', 'projectRecord');
        Route::put('/projects/{project}/goal', 'goal');
        Route::post('/projects/{project}/stages', 'stage');
        Route::put('/member-stages/order', 'memberStageOrder');
        Route::post('/member-stages', 'personalStage');
        Route::match(['put', 'delete'], '/member-stages/{stage}', 'personalStage');
        Route::put('/projects/{project}/stages/{kind}/order', 'order')->whereIn('kind', ['product', 'member']);
        Route::match(['put', 'delete'], '/projects/{project}/stages/{kind}/{id}', 'updateStage')->whereIn('kind', ['product', 'member']);
        Route::get('/tasks/{task}', 'taskDetail');
        Route::post('/tasks', 'saveTask');
        Route::put('/tasks/reorder', 'reorderTasks');
        Route::put('/tasks/{task}', 'saveTask');
        Route::patch('/tasks/{task}/complete', 'complete');
        Route::patch('/tasks/{task}/product-stage', 'moveTaskStage');
        Route::delete('/tasks/{task}', 'removeTask');
        Route::post('/labels', 'label');
        Route::match(['put', 'delete'], '/labels/{label}', 'label');
        Route::post('/tasks/{task}/comments', 'comment');
        Route::match(['post', 'put', 'delete'], '/comments/{comment}', 'editComment');
        Route::post('/tasks/{task}/attachments', 'upload');
        Route::delete('/attachments/{attachment}', 'removeAttachment');
        Route::post('/notifications/read', 'notifications');
        Route::post('/notifications/{notification}/read', 'notifications');
        Route::put('/settings', 'settings');
        Route::post('/settings/avatar', 'uploadAvatar');
        Route::delete('/settings/avatar', 'removeAvatar');
    });
});
