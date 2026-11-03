<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SigningController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('documents.index') : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/documents/{document}/void', [DocumentController::class, 'void'])->name('documents.void');
    Route::post('/documents/{document}/signers/{signer}/remind', [DocumentController::class, 'remind'])->name('documents.remind');
});

Route::prefix('sign/{token}')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [SigningController::class, 'show'])->name('sign.show');
    Route::get('/file', [SigningController::class, 'file'])->name('sign.file');
    Route::get('/download', [SigningController::class, 'download'])->name('sign.download');
    Route::post('/', [SigningController::class, 'submit'])->name('sign.submit');
    Route::post('/decline', [SigningController::class, 'decline'])->name('sign.decline');
});
