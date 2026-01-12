<?php

use App\Http\Controllers\Api\ChatFileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Route::middleware(['auth:sanctum'])->prefix('chat')->name('chat.')->group(function () {
Route::prefix('chat')->name('chat.')->group(function () {

    // 📤 رفع الملفات (Upload)
    Route::prefix('upload')->name('upload.')->group(function () {
        Route::post('/image', [ChatFileController::class, 'uploadImage'])->name('image');
        Route::post('/video', [ChatFileController::class, 'uploadVideo'])->name('video');
        Route::post('/audio', [ChatFileController::class, 'uploadAudio'])->name('audio');
        Route::post('/document', [ChatFileController::class, 'uploadDocument'])->name('document');
    });

    // Route::get('/download/{attachmentId}', [ChatFileController::class, 'downloadFile'])->name('download');

    // 🗑️ حذف ملف (Delete)
    Route::delete('/file/{attachmentId}', [ChatFileController::class, 'deleteFile'])->name('file.delete');

    // 📋 الحصول على ملفات رسالة (Get Files)
    Route::get('/message/{messageId}/files', [ChatFileController::class, 'getMessageFiles'])->name('message.files');

});
