<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\SearchController;

Route::get('/', function () {
    return view('upload');
});

Route::post('/upload', [PdfController::class, 'upload'])->name('upload');

Route::post('/search', [SearchController::class, 'search'])->name('search');

Route::get('/question', function () {
    return view('question');
});

Route::get('/response/{id}', [SearchController::class, 'response'])->name('response');
