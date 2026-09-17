<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::view('/login', 'auth.login')
    ->name('login');

Route::view('/register', 'auth.register')
    ->name('register');

Route::view('/forgot-password', 'auth.forgot-password')
    ->name('password.request');

Route::get('/reset-password/{token}', function (
    Request $request,
    string $token
) {
    return view('auth.reset-password', [
        'token' => $token,
        'email' => $request->query('email'),
    ]);
})
    ->name('password.reset');