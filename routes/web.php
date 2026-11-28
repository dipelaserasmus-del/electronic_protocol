<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\KeepvalueController;
use App\Http\Controllers\ProtocolFormController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\PrintController;
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
    Route::get('users', [UserController::class, 'index']);   // R6a: App\Livewire\UserAdmin (save/delete are its actions)
    Route::get('users/{user}', [UserController::class, 'index'])->whereNumber('user');

    Route::get('keep', [KeepvalueController::class, 'index']);   // R6b: App\Livewire\FolderAdmin (changes are its actions)
    Route::get('keep/{keepvalue}', [KeepvalueController::class, 'index'])->whereNumber('keepvalue');

    Route::get('/home', [ProtocolFormController::class, 'create']);   // R1b
    Route::get('/chkForUpdates', [ProtocolController::class, 'chkForUpdates']);
    Route::get('/home/list', [ProtocolFormController::class, 'list'])->middleware(['redirectAfterLogin']);   // R2
    Route::get('/home/list/{filter?}/{userId?}', [ProtocolFormController::class, 'list']);
    Route::get('/home/{protocol}/{copyAsNew?}', [ProtocolFormController::class, 'show']);   // R1b
    Route::get('/protocol/{num}/{year?}', [ProtocolFormController::class, 'link']);   // R3h: direct link

    Route::post('/home', [ProtocolController::class, 'store']);
    Route::post('/home/{protocol}', [ProtocolController::class, 'update']);

    Route::get('/goto/{etos}/{protocolnum}/{step?}', [ProtocolController::class, 'gotonum']);
    Route::get('/download/{attachment}', [ProtocolController::class, 'download']);
    Route::get('/preview/{attachment}', [ProtocolController::class, 'preview'])->whereNumber('attachment');   // R4c
    Route::post('/attachments/{attachment}/stamp-place', [\App\Http\Controllers\StampPlaceController::class, 'update'])
        ->whereNumber('attachment')->middleware('throttle:60,1');   // R4n: the stamp's place on one file
    Route::get('/trash', [ProtocolFormController::class, 'trash']);   // R4b (administrators)
    Route::get('/trash/{attachment}/download', [ProtocolFormController::class, 'trashDownload'])->whereNumber('attachment');

    Route::view('/checks', 'checks');   // A01: App\Livewire\IntegrityChecks (its mount checks the role)
    Route::view('/history', 'history');   // X02: App\Livewire\AuditHistory (administrators: its boot checks the role)
    Route::get('/find', [ProtocolFormController::class, 'search']);   // R3a (old /getFindData removed)
    Route::get('/search', [SearchController::class, 'results']);   // R3c: results of the top-bar box
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:120,1');
    Route::post('/preferences', [PreferencesController::class, 'update'])->middleware('throttle:60,1');   // R3d
    Route::get('/notes', [\App\Http\Controllers\NotesController::class, 'show']);   // N05: the user's notepad
    Route::post('/notes', [\App\Http\Controllers\NotesController::class, 'update'])->middleware('throttle:120,1');
    // autocomplete: the forms send the search term as ?term=... (path form kept for compatibility)
    Route::get('/getValues/{field}/{id}/{divId}/{multi}', [ProtocolController::class, 'getValuesQuery']);
    Route::get('/getValues/{term}/{field}/{id}/{divId}/{multi}', [ProtocolController::class, 'getValues']);

    // R7: printing (App\Http\Controllers\PrintController, App\Livewire\PrintPage); the e-mail receipt is the protocol
    // form's action (ProtocolWriter::sendReceipt), the old POST receiptToEmail is gone
    Route::get('/print', [PrintController::class, 'page']);
    Route::match(['get', 'post'], '/printed', [PrintController::class, 'book']);
    Route::get('/printAttachments', [PrintController::class, 'attachmentsPage']);
    Route::match(['get', 'post'], '/printedAttachments', [PrintController::class, 'attachments']);
    Route::get('/receipt/{protocol}', [PrintController::class, 'receipt']);
    Route::get('/about', [ProtocolController::class, 'about']);

    // R5c: the inbox (App\Livewire\EmailInbox); registering and "read" are actions of the page
    Route::get('/inbox', [InboxController::class, 'show']);
    Route::get('/inbox/count', [InboxController::class, 'count'])->middleware('throttle:30,1');   // R5f: the badge
    Route::get('/viewEmails', [InboxController::class, 'old']);
    Route::get('/viewEmailAttachment/{uid}/{key}', [InboxController::class, 'attachment']);
    Route::get('/previewEmailAttachment/{uid}/{key}', [InboxController::class, 'preview']);   // R4c
    Route::get('/viewEmailHtml/{uid}', [InboxController::class, 'html']);

    // R6c: Livewire pages; saving, creating and deleting are their actions (no more GET links that change things)
    Route::get('/settings', [ConfigController::class, 'index']);
    Route::get('/stamp', [\App\Http\Controllers\StampController::class, 'show']);   // R4e: the stamp designer (administrators, R4i)
    Route::get('/backups', [ConfigController::class, 'backups']);
    Route::get('/logs', [ConfigController::class, 'logs']);   // R6e: log viewer (administrators)
    Route::get('/downloadBackup/{name}', [ConfigController::class, 'downloadBackup']);
    Route::get('/arxeio', [ConfigController::class, 'arxeio']);
    Route::get('/expired/{deleted?}', [ConfigController::class, 'expired']);
});

// N04: the shared components in one page (browser tests, trying them out). Never in production.
if (app()->environment(['local', 'development', 'testing'])) {
    Route::view('/dev/ui', 'dev.ui')->middleware('auth');
}
