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

Route::get('/openapi.yaml', function () {
    return response()->file(base_path('openapi.yaml'), ['Content-Type' => 'application/yaml']);
});

Route::get('/api-docs', function () {
    return response(<<<'HTML'
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="utf-8" />
            <title>Шаблонизатор договоров — API</title>
            <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
            <script>
                window.onload = () => SwaggerUIBundle({ url: '/openapi.yaml', dom_id: '#swagger-ui' });
            </script>
        </body>
        </html>
        HTML);
});
