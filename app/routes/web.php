<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// This is a pure REST API project with no login page. Laravel's auth
// middleware still resolves route('login') for unauthenticated browser
// requests (Accept: text/html), so without this it 500s instead of 401ing.
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');
