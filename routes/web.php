<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\KeepvalueController;
use App\Http\Controllers\ProtocolFormController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ProtocolController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
	$allowregister = \App\Support\Registration::isOpen();
	return view('welcome', compact('allowregister'));
});

//Auth::routes();
// Authentication Routes...
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Registration Routes... (open only until the first administrator exists, see App\Support\Registration)
Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('register', [RegisterController::class, 'register'])->middleware('throttle:5,1');

// Password Reset Routes...
Route::get('password/resetForm', [ForgotPasswordController::class, 'showLinkRequestForm']);
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::get('password/reset', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('password.reset');
// End Auth::routes();

// Everything below needs a logged-in user; each controller adds its role checks (HasMiddleware).
Route::middleware('auth')->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{user}', [UserController::class, 'index']);
    Route::post('users/{user}', [UserController::class, 'update']);
    Route::get('users/del/{user}', [UserController::class, 'delete']);

    Route::get('keep', [KeepvalueController::class, 'index']);
    Route::post('keep', [KeepvalueController::class, 'store']);
    Route::get('keep/{keepvalue}', [KeepvalueController::class, 'index']);
    Route::post('keep/{keepvalue}', [KeepvalueController::class, 'update']);
    Route::get('keep/del/{keepvalue}', [KeepvalueController::class, 'delete']);

    Route::get('/home', [ProtocolFormController::class, 'create']);   // R1b
    Route::get('/chkForUpdates', [ProtocolController::class, 'chkForUpdates']);
    Route::get('/home/list', [ProtocolFormController::class, 'list'])->middleware(['redirectAfterLogin']);   // R2
    Route::get('/home/list/{filter?}/{userId?}', [ProtocolFormController::class, 'list']);
    Route::get('/home/{protocol}/{copyAsNew?}', [ProtocolFormController::class, 'show']);   // R1b
    Route::get('getKeep4Fakelos/{fakelos}', [ProtocolController::class, 'getKeep4Fakelos']);

    Route::post('/home', [ProtocolController::class, 'store']);
    Route::post('/home/{protocol}', [ProtocolController::class, 'update']);
    Route::post('/checkSameEmail', [ProtocolController::class, 'checkSameEmail'])->name('checkSameEmail');

    Route::get('/goto/{etos}/{protocolnum}/{step?}', [ProtocolController::class, 'gotonum']);
    Route::get('/download/{attachment}', [ProtocolController::class, 'download']);
    Route::get('/trash', [ProtocolFormController::class, 'trash']);   // R4b (administrators)
    Route::get('/trash/{attachment}/download', [ProtocolFormController::class, 'trashDownload'])->whereNumber('attachment');

    Route::get('/find', [ProtocolFormController::class, 'search']);   // R3a (old /getFindData removed)
    Route::get('/search', [SearchController::class, 'results']);   // R3c: results of the top-bar box
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:120,1');
    Route::post('/preferences', [PreferencesController::class, 'update'])->middleware('throttle:60,1');   // R3d
    // autocomplete: the forms send the search term as ?term=... (path form kept for compatibility)
    Route::get('/getValues/{field}/{id}/{divId}/{multi}', [ProtocolController::class, 'getValuesQuery']);
    Route::get('/getValues/{term}/{field}/{id}/{divId}/{multi}', [ProtocolController::class, 'getValues']);

    Route::get('/print', [ProtocolController::class, 'printprotocols']);
    Route::post('/printed', [ProtocolController::class, 'printed']);
    Route::get('/printAttachments', [ProtocolController::class, 'printAttachments']);
    Route::post('/printedAttachments', [ProtocolController::class, 'printedAttachments']);

    Route::get('/receipt/{protocol}', [ProtocolController::class, 'receipt']);
    Route::post('receiptToEmail', [ProtocolController::class, 'receiptToEmail'])->name('receiptToEmail');
    Route::get('/about', [ProtocolController::class, 'about']);
    Route::get('/updated', [ProtocolController::class, 'updated']);

    Route::get('/viewEmails', [ProtocolController::class, 'viewEmails']);
    Route::get('/getEmailNum', [ProtocolController::class, 'getEmailNum']);
    Route::get('/viewEmailAttachment/{messageUid}/{attachmentKey}', [ProtocolController::class, 'viewEmailAttachment']);
    Route::get('/viewEmailHtml/{messageUid}', [ProtocolController::class, 'viewEmailHtml']);
    Route::get('/setEmailRead/{messageUid}', [ProtocolController::class, 'setEmailRead']);
    Route::post('/storeFromEmail', [ProtocolController::class, 'storeFromEmail']);

    Route::get('/settings', [ConfigController::class, 'index']);
    Route::post('/settings', [ConfigController::class, 'store']);

    Route::get('/backups', [ConfigController::class, 'backups']);
    Route::get('/backup', [ConfigController::class, 'backup']);
    Route::get('/downloadBackup/{name}', [ConfigController::class, 'downloadBackup']);
    Route::get('/deleteBackup/{name}', [ConfigController::class, 'deleteBackup']);

    Route::get('/arxeio', [ConfigController::class, 'arxeio']);
    Route::get('/expired/{deleted?}', [ConfigController::class, 'expired']);
    Route::get('/delExpired', [ConfigController::class, 'delExpired']);
    Route::get('/delDeleted', [ConfigController::class, 'delDeleted']);
});

// N04: the shared components in one page (browser tests, trying them out). Never in production.
if (app()->environment(['local', 'development', 'testing'])) {
    Route::view('/dev/ui', 'dev.ui')->middleware('auth');
}
