<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Community\CommunityController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Game\GameController;
use App\Http\Controllers\Player\PlayerController;
use App\Http\Controllers\Player\PlayerCourtBookingController;
use App\Http\Controllers\Scoring\ScoringController;
use App\Http\Controllers\Venue\CourtBookingController;
use App\Http\Controllers\Venue\CourtController;
use App\Http\Controllers\Venue\VenueController;
use App\Http\Controllers\Venue\VenueFacilityController;
use App\Http\Controllers\Venue\VenueOwnerApiController;
use App\Http\Controllers\Venue\VenueOwnerDashboardController;
use App\Http\Middleware\RequireVenueOwnerApi;
use Illuminate\Support\Facades\Route;

// Auth Routes (Login, Register, Logout)
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf.token');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Well-Known endpoints for Deep Links (Android App Links & iOS Universal Links)
Route::get('/.well-known/assetlinks.json', function () {
    $path = public_path('.well-known/assetlinks.json');
    if (file_exists($path)) {
        return response(file_get_contents($path), 200, ['Content-Type' => 'application/json']);
    }

    return response()->json([], 404);
});
Route::get('/.well-known/apple-app-site-association', function () {
    $path = public_path('.well-known/apple-app-site-association');
    if (file_exists($path)) {
        return response(file_get_contents($path), 200, ['Content-Type' => 'application/json']);
    }

    return response()->json([], 404);
});

// Dashboard & Home (Public)
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Game & Mabar
Route::prefix('games')->name('games.')->group(function () {
    Route::get('/', [GameController::class, 'index'])->name('index');
    Route::get('/share/{token}', [GameController::class, 'share'])->name('share');
    Route::get('/{id}', [GameController::class, 'show'])->whereNumber('id')->name('show');
    Route::get('/share/{token}', [GameController::class, 'share'])->name('share');
    Route::get('/{id}/drawing', [GameController::class, 'drawing'])->whereNumber('id')->name('drawing');
    Route::post('/{id}/join', [GameController::class, 'joinSession'])->whereNumber('id')->name('join'); // Support Member & Guest Player join

    // Protected: Lock Drawing, Create Game Wizard, Schedule Sesi Mabar
    Route::middleware('auth')->group(function () {
        Route::post('/{id}/lock', [GameController::class, 'lockDrawing'])->whereNumber('id')->name('lock');
        Route::get('/create', [GameController::class, 'create'])->name('create');
        Route::get('/players/search', [GameController::class, 'searchPlayers'])->name('players.search');
        Route::post('/', [GameController::class, 'store'])->name('store');
        Route::get('/schedule', [GameController::class, 'createSchedule'])->name('schedule');
        Route::post('/schedule', [GameController::class, 'storeSchedule'])->name('schedule.post');
        Route::post('/{id}/cancel', [GameController::class, 'cancelSession'])->whereNumber('id')->name('cancel');
        Route::delete('/bulk-destroy', [GameController::class, 'bulkDestroy'])->name('bulkDestroy');
        Route::delete('/{id}', [GameController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });
});

Route::get('/mabar/share/{token}', [GameController::class, 'share'])->name('mabar.share');

// Venues & Courts
Route::prefix('venues')->name('venues.')->group(function () {
    Route::get('/', [VenueController::class, 'index'])->name('index');
    Route::get('/{id}/court-selection', [VenueController::class, 'courtSelectionPlaceholder'])->whereNumber('id')->name('court-selection');
    Route::get('/{id}', [VenueController::class, 'show'])->whereNumber('id')->name('show');

    // Protected: SMK 2 — Venue & Court Manager
    Route::middleware('auth')->group(function () {
        Route::post('/quick-store', [VenueController::class, 'quickStore'])->name('quickStore'); // Quick Add Venue from Session Wizard
        Route::get('/create', [VenueController::class, 'create'])->name('create');
        Route::post('/', [VenueController::class, 'store'])->name('store');             // [SMK 2] Simpan venue baru ke DB
        Route::get('/{id}/edit', [VenueController::class, 'edit'])->whereNumber('id')->name('edit');     // Form edit venue
        Route::put('/{id}', [VenueController::class, 'update'])->whereNumber('id')->name('update');      // Simpan perubahan venue ke DB
        Route::delete('/{id}', [VenueController::class, 'destroy'])->whereNumber('id')->name('destroy');   // Hapus / nonaktifkan venue
        Route::post('/{id}/photos', [VenueController::class, 'updatePhotos'])->whereNumber('id')->name('photos.update'); // Tambah/Hapus foto venue
        Route::get('/{id}/photos', fn ($id) => redirect()->route('venues.show', $id)); // Graceful fallback if opened via GET
        Route::get('/{id}/courts', [CourtController::class, 'index'])->whereNumber('id')->name('courts.index');   // [SMK 2] List court per venue
        Route::get('/{id}/courts/create', [CourtController::class, 'create'])->whereNumber('id')->name('courts.create'); // [SMK 2] Form tambah court
        Route::post('/{id}/courts', [CourtController::class, 'store'])->whereNumber('id')->name('courts.store');  // [SMK 2] Simpan court baru ke DB
        Route::put('/{id}/courts/{courtId}', [CourtController::class, 'update'])->whereNumber(['id', 'courtId'])->name('courts.update'); // Update court
        Route::delete('/{id}/courts/{courtId}', [CourtController::class, 'destroy'])->whereNumber(['id', 'courtId'])->name('courts.destroy'); // Hapus court
        Route::delete('/bulk-destroy', [VenueController::class, 'bulkDestroy'])->name('bulkDestroy'); // Bulk hapus venue
    });
});

// Venue Owner Portal
Route::prefix('owner')->name('owner.')->middleware('auth')->group(function () {
    Route::get('/dashboard', [VenueOwnerDashboardController::class, 'index'])->name('dashboard');
    Route::post('/venues/{venueId}/facilities', [VenueFacilityController::class, 'store'])->whereNumber('venueId')->name('facilities.store');
    Route::put('/venues/{venueId}/facilities/{facilityId}', [VenueFacilityController::class, 'update'])->whereNumber(['venueId', 'facilityId'])->name('facilities.update');
    Route::delete('/venues/{venueId}/facilities/{facilityId}', [VenueFacilityController::class, 'destroy'])->whereNumber(['venueId', 'facilityId'])->name('facilities.destroy');
    Route::post('/courts/{courtId}/schedule', [VenueOwnerDashboardController::class, 'saveSchedule'])->whereNumber('courtId')->name('courts.schedule');
    Route::post('/courts/{courtId}/blocks', [VenueOwnerDashboardController::class, 'blockSlot'])->whereNumber('courtId')->name('courts.blocks.store');
    Route::delete('/courts/{courtId}/blocks/{blockId}', [VenueOwnerDashboardController::class, 'unblockSlot'])->whereNumber(['courtId', 'blockId'])->name('courts.blocks.destroy');
    Route::post('/courts/{courtId}/bookings', [CourtBookingController::class, 'storeManual'])->whereNumber('courtId')->name('bookings.store');
    Route::post('/bookings/{bookingId}/cancel', [CourtBookingController::class, 'cancel'])->whereNumber('bookingId')->name('bookings.cancel');
});

Route::prefix('api/v1/owner')
    ->name('api.v1.owner.')
    ->middleware(RequireVenueOwnerApi::class)
    ->group(function () {
        Route::get('/sports', [VenueOwnerApiController::class, 'sports'])->name('sports.index');
        Route::get('/venues', [VenueOwnerApiController::class, 'venues'])->name('venues.index');
        Route::post('/venues', [VenueController::class, 'store'])->name('venues.store');
        Route::get('/venues/{venueId}', [VenueOwnerApiController::class, 'venue'])->whereNumber('venueId')->name('venues.show');
        Route::put('/venues/{venueId}', [VenueController::class, 'update'])->whereNumber('venueId')->name('venues.update');
        Route::delete('/venues/{venueId}', [VenueController::class, 'destroy'])->whereNumber('venueId')->name('venues.destroy');

        Route::post('/venues/{venueId}/facilities', [VenueFacilityController::class, 'store'])->whereNumber('venueId')->name('facilities.store');
        Route::put('/venues/{venueId}/facilities/{facilityId}', [VenueFacilityController::class, 'update'])->whereNumber(['venueId', 'facilityId'])->name('facilities.update');
        Route::delete('/venues/{venueId}/facilities/{facilityId}', [VenueFacilityController::class, 'destroy'])->whereNumber(['venueId', 'facilityId'])->name('facilities.destroy');

        Route::post('/venues/{venueId}/courts', [CourtController::class, 'store'])->whereNumber('venueId')->name('courts.store');
        Route::put('/venues/{venueId}/courts/{courtId}', [CourtController::class, 'update'])->whereNumber(['venueId', 'courtId'])->name('courts.update');
        Route::delete('/venues/{venueId}/courts/{courtId}', [CourtController::class, 'destroy'])->whereNumber(['venueId', 'courtId'])->name('courts.destroy');

        Route::get('/courts/{courtId}/schedule', [VenueOwnerApiController::class, 'schedule'])->whereNumber('courtId')->name('courts.schedule.show');
        Route::put('/courts/{courtId}/schedule', [VenueOwnerDashboardController::class, 'saveSchedule'])->whereNumber('courtId')->name('courts.schedule.update');
        Route::post('/courts/{courtId}/blocks', [VenueOwnerDashboardController::class, 'blockSlot'])->whereNumber('courtId')->name('courts.blocks.store');
        Route::delete('/courts/{courtId}/blocks/{blockId}', [VenueOwnerDashboardController::class, 'unblockSlot'])->whereNumber(['courtId', 'blockId'])->name('courts.blocks.destroy');

        Route::get('/bookings', [VenueOwnerApiController::class, 'bookings'])->name('bookings.index');
        Route::post('/courts/{courtId}/bookings', [CourtBookingController::class, 'storeManual'])->whereNumber('courtId')->name('bookings.store');
        Route::post('/bookings/{bookingId}/cancel', [CourtBookingController::class, 'cancel'])->whereNumber('bookingId')->name('bookings.cancel');
    });

// Live Match Scoring Console & Recap
Route::prefix('scoring')->name('scoring.')->group(function () {
    Route::get('/live/{id?}', [ScoringController::class, 'live'])->name('live');
    Route::get('/recap/{id?}', [ScoringController::class, 'recap'])->name('recap');
    Route::get('/get-score/{gameId}/{round?}', [ScoringController::class, 'getScore'])->name('get-score'); // JSON polling
    Route::post('/update-score', [ScoringController::class, 'updateScore'])->name('update-score'); // AJAX endpoint
    Route::post('/stop-match', [ScoringController::class, 'stopMatch'])->name('stop-match');       // Akhiri pertandingan (End Match)
    Route::post('/reactivate-player', [ScoringController::class, 'reactivatePlayer'])->name('reactivate-player'); // Aktifkan kembali pemain dari istirahat
    Route::post('/confirm-adjusted-schedule', [ScoringController::class, 'confirmAdjustedSchedule'])->name('confirm-adjusted-schedule'); // Konfirmasi jadwal atomik
    Route::post('/manage-player-status', [ScoringController::class, 'managePlayerStatus'])->name('manage-player-status'); // Kelola pemain dari drawing
    Route::post('/next-round', [ScoringController::class, 'nextRound'])->name('next-round');       // Lanjut ke set/ronde berikutnya
    Route::post('/finish', [ScoringController::class, 'finishSession'])->name('finish');           // Selesaikan sesi
    Route::post('/kudos/toggle', [ScoringController::class, 'toggleKudos'])->name('kudos.toggle'); // Simpan / Cabut Kudos permanen ke DB
});

// Player Recap (Public Share & Guest-Accessible)
Route::get('/player/match-recap/{id?}', [PlayerController::class, 'recap'])->name('player.recap');
Route::get('/player/recap/{id?}', [PlayerController::class, 'recap']);
Route::get('/recap/user/{id}', [PlayerController::class, 'recap'])->name('recap.user');

// Protected: Player Profile & Host Management
Route::prefix('player')->name('player.')->middleware('auth')->group(function () {
    Route::get('/profile', [PlayerController::class, 'profile'])->name('profile');
    Route::post('/profile', [PlayerController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/toggle-host', [PlayerController::class, 'toggleHost'])->name('profile.toggle-host');
});

// User / Player Court Dashboard & Bookings
Route::middleware('auth')->group(function () {
    Route::get('/user/dashboard', [PlayerCourtBookingController::class, 'dashboard'])->name('user.dashboard');
    Route::post('/user/bookings', [PlayerCourtBookingController::class, 'store'])->name('player.bookings.store');
    Route::get('/player/bookings', [PlayerCourtBookingController::class, 'myBookings'])->name('player.bookings.index');
});

// Community
Route::prefix('communities')->name('communities.')->group(function () {
    Route::get('/', [CommunityController::class, 'index'])->name('index');
    Route::get('/show', fn () => abort(404, 'ID Komunitas diperlukan.'))->name('show.empty');
    Route::get('/detail', fn () => abort(404, 'ID Komunitas diperlukan.'))->name('detail.empty');
    Route::get('/{id}', [CommunityController::class, 'show'])->whereNumber('id')->name('show'); // [SMK 3] Detail komunitas

    // Protected: SMK 3 — Community Manager
    Route::middleware('auth')->group(function () {
        Route::get('/create', [CommunityController::class, 'create'])->name('create');
        Route::post('/upload-logo', [CommunityController::class, 'uploadLogo'])->name('upload-logo');
        Route::post('/', [CommunityController::class, 'store'])->name('store');                  // [SMK 3] Simpan komunitas baru ke DB
        Route::get('/{id}/edit', [CommunityController::class, 'edit'])->whereNumber('id')->name('edit');     // Form edit komunitas
        Route::put('/{id}', [CommunityController::class, 'update'])->whereNumber('id')->name('update');      // Simpan perubahan komunitas
        Route::delete('/bulk-destroy', [CommunityController::class, 'bulkDestroy'])->name('bulkDestroy');    // Bulk hapus komunitas
        Route::delete('/{id}', [CommunityController::class, 'destroy'])->whereNumber('id')->name('destroy'); // Nonaktif / Hapus komunitas
        Route::post('/{id}/join', [CommunityController::class, 'join'])->whereNumber('id')->name('join');   // [SMK 3] Join komunitas
        Route::post('/{id}/leave', [CommunityController::class, 'leave'])->whereNumber('id')->name('leave'); // [SMK 3] Leave komunitas
    });
});

Route::get('/community/{id?}', function ($id = null) {
    if (! $id || ! is_numeric($id)) {
        abort(404, 'Komunitas tidak ditemukan.');
    }

    return redirect()->route('communities.show', (int) $id);
});

// Admin — Manajemen Pengguna
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [PlayerController::class, 'manageUsers'])->name('users');
    Route::put('/users/{id}', [PlayerController::class, 'updateUser'])->whereNumber('id')->name('users.update');
    Route::delete('/users/{id}', [PlayerController::class, 'destroyUser'])->whereNumber('id')->name('users.destroy');
});

// Booking Slots & Venue Bookings
Route::prefix('venues')->name('venues.')->group(function () {
    Route::get('/courts/{courtId}/booking-slots', [\App\Http\Controllers\Venue\BookingController::class, 'slots'])->name('booking-slots');
    Route::middleware('auth')->group(function () {
        Route::post('/bookings', [\App\Http\Controllers\Venue\BookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{bookingId}', [\App\Http\Controllers\Venue\BookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{bookingId}/cancel', [\App\Http\Controllers\Venue\BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('/bookings/{bookingId}/confirm', [\App\Http\Controllers\Venue\BookingController::class, 'confirm'])->name('bookings.confirm');
    });
});
