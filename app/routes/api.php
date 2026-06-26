<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\VariableController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/templates', [TemplateController::class, 'index']);
    Route::get('/templates/{template}', [TemplateController::class, 'show']);

    Route::middleware('role:admin,methodologist')->group(function () {
        Route::post('/templates', [TemplateController::class, 'store']);
        Route::put('/templates/{template}', [TemplateController::class, 'update']);
        Route::post('/templates/{template}/versions', [TemplateController::class, 'storeVersion']);
        Route::post('/templates/{template}/variables/extract', [TemplateController::class, 'extractVariables']);
        Route::post('/templates/{template}/publish', [TemplateController::class, 'publish']);

        Route::post('/templates/{template}/variables', [VariableController::class, 'store']);
        Route::put('/variables/{variable}', [VariableController::class, 'update']);
        Route::delete('/variables/{variable}', [VariableController::class, 'destroy']);
    });

    Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])
        ->middleware('role:admin');

    Route::post('/templates/{template}/documents', [DocumentController::class, 'store']);
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
});
