<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\DoctorAuthController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::group([
    'prefix' => 'user/auth'
], function ($router) {
    Route::post('/login', [UserAuthController::class, 'login']);
    Route::post('/register', [UserAuthController::class, 'register']);
    Route::post('/logout', [UserAuthController::class, 'logout']);
    Route::post('/refresh', [UserAuthController::class, 'refresh']);
    Route::get('/user-profile', [UserAuthController::class, 'userProfile']);
});

Route::group([
    'prefix' => 'doctor/auth'
], function ($router) {
    Route::post('/login', [DoctorAuthController::class, 'login']);
    Route::post('/register', [DoctorAuthController::class, 'register']);
    Route::post('/logout', [DoctorAuthController::class, 'logout']);
    Route::post('/refresh', [DoctorAuthController::class, 'refresh']);
    Route::get('/user-profile', [DoctorAuthController::class, 'userProfile']);
});

/**
 * Authentication for pusher private channels
 */
Route::post('/user/chat/auth', [MessagesController::class, 'pusherAuth'])->middleware('auth:user');
Route::post('/doctor/chat/auth', [MessagesController::class, 'pusherDoctorAuth'])->middleware('auth:doctor');

/**
 *  Fetch info for specific id [user/doctor]
 */
Route::post('/user/chat/idInfo', [MessagesController::class, 'idDoctorFetchData'])->middleware('auth:user');
Route::post('/doctor/chat/idInfo', [MessagesController::class, 'idFetchData'])->middleware('auth:doctor');

/**
 * Send message route
 */
Route::post('/user/chat/sendMessage', [MessagesController::class, 'send'])->middleware('auth:user');
Route::post('/doctor/chat/sendMessage', [MessagesController::class, 'send'])->middleware('auth:doctor');


/**
 * Fetch messages
 */
Route::post('/user/chat/fetchMessages', [MessagesController::class, 'fetch'])->middleware('auth:user');
Route::post('/doctor/chat/fetchMessages', [MessagesController::class, 'fetch'])->middleware('auth:doctor');


/**
 * Download attachments route to create a downloadable links
 */
Route::get('/user/chat/download/{fileName}', [MessagesController::class, 'download'])->middleware('auth:user');
Route::get('/doctor/chat/download/{fileName}', [MessagesController::class, 'download'])->middleware('auth:doctor');


/**
 * Make messages as seen
 */
Route::post('/user/chat/makeSeen', [MessagesController::class, 'seen'])->middleware('auth:user');
Route::post('/doctor/chat/makeSeen', [MessagesController::class, 'seen'])->middleware('auth:doctor');


/**
 * Get contacts
 */
Route::get('/doctor/chat/getDoctorContacts', [MessagesController::class, 'getDoctorContacts'])->middleware('auth:doctor');
Route::get('/user/chat/getUserContacts', [MessagesController::class, 'getUserContacts'])->middleware('auth:user');



/**
 * Get shared photos
 */
Route::post('/doctor/chat/shared', [MessagesController::class, 'sharedPhotos'])->middleware('auth:doctor');
Route::post('/user/chat/shared', [MessagesController::class, 'sharedPhotos'])->middleware('auth:user');


/**
 * Delete Conversation
 */
Route::post('/user/chat/deleteConversation', [MessagesController::class, 'deleteConversation'])->middleware('auth:user');
Route::post('/doctor/chat/deleteConversation', [MessagesController::class, 'deleteConversation'])->middleware('auth:doctor');