<?php

use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\AutoSpeedReportController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\officer\ContactMessageController as OfficerContactMessageController;
use App\Http\Controllers\officer\OfficerDashboardController;
use App\Http\Controllers\officer\OfficerHotspotController;
use App\Http\Controllers\officer\OfficerNotificationController;
use App\Http\Controllers\officer\OfficerProfileController;
use App\Http\Controllers\officer\OfficerReportController;
use App\Http\Controllers\officer\RoadSegmentController;
use App\Http\Controllers\officer\SegmentTypeController;
use App\Http\Controllers\officer\ViolationTypeController;
use App\Http\Controllers\PublicHotspotController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

// Public landing pages and contact entry points.
Route::view('/', 'home')->name('home');
Route::view('/home', 'home');
Route::view('/about', 'about')->name('about');
Route::view('/privacy', 'privacy')->name('privacy');
Route::redirect('/help', '/contact')->name('help');
Route::get('/contact', [ContactMessageController::class, 'create'])->name('contact');
Route::post('/contact', [ContactMessageController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');
Route::view('/developer', 'developer')->name('developer');
Route::get('/hotspots', [PublicHotspotController::class, 'index'])->name('hotspots.index');
Route::redirect('/news-events', '/hotspots')->name('news-events');
// Officer dashboard shortcuts and data endpoints.
Route::get('/road-officer/dashboard', [OfficerDashboardController::class, 'index'])->middleware('auth')->name('officer.dashboard');
Route::get('/roadofficer/dashboard', [OfficerDashboardController::class, 'index'])->middleware('auth')->name('roadofficer.dashboard');
Route::get('/maps/reverse-geocode', [MapController::class, 'reverseGeocode'])
    ->middleware('throttle:30,1')
    ->name('maps.reverse-geocode');
Route::get('/maps/search', [MapController::class, 'search'])
    ->middleware('throttle:60,1')
    ->name('maps.search');
Route::post('/auto-speed-reports/evaluate', [AutoSpeedReportController::class, 'evaluate'])
    ->middleware('throttle:180,1')
    ->name('auto-speed-reports.evaluate');
Route::post('/auto-speed-reports', [AutoSpeedReportController::class, 'store'])
    ->middleware('throttle:12,1')
    ->name('auto-speed-reports.store');

// Protected officer tools that require authentication.
Route::middleware('auth')->group(function () {
    Route::get('/road-officer/notifications', [OfficerNotificationController::class, 'index'])->name('officer.notifications.index');
    Route::get('/road-officer/hotspots', [OfficerHotspotController::class, 'index'])->name('officer.hotspots.index');
    Route::get('/road-officer/notifications/dropdown-data', [OfficerNotificationController::class, 'dropdownData'])->name('officer.notifications.dropdown-data');
    Route::post('/road-officer/notifications/mark-all-read', [OfficerNotificationController::class, 'markAllRead'])->name('officer.notifications.mark-all-read');
    Route::get('/road-officer/notifications/{notificationId}', [OfficerNotificationController::class, 'show'])->name('officer.notifications.show');
    Route::get('/road-officer/contact-messages', [OfficerContactMessageController::class, 'index'])->name('officer.contact-messages.index');
    Route::get('/road-officer/contact-messages/{contactMessage}', [OfficerContactMessageController::class, 'show'])->name('officer.contact-messages.show');
    Route::put('/road-officer/contact-messages/{contactMessage}', [OfficerContactMessageController::class, 'update'])->name('officer.contact-messages.update');
    Route::delete('/road-officer/contact-messages/{contactMessage}', [OfficerContactMessageController::class, 'destroy'])->name('officer.contact-messages.destroy');
    Route::get('/road-officer/reports', [OfficerReportController::class, 'index'])->name('officer.reports.index');
    Route::get('/road-officer/reports/{report}', [OfficerReportController::class, 'show'])->name('officer.reports.show');
    Route::put('/road-officer/reports/{report}', [OfficerReportController::class, 'update'])->name('officer.reports.update');
    Route::redirect('/road-officer/segment-rules', '/road-officer/segment-types')
        ->name('officer.segment-rules.index');
    Route::redirect('/road-officer/road-rules', '/road-officer/segment-types')
        ->name('officer.road-rules.index');
    Route::get('/road-officer/road-segments', [RoadSegmentController::class, 'index'])->name('officer.road-segments.index');
    Route::get('/road-officer/road-segments/manage', [RoadSegmentController::class, 'manage'])->name('officer.road-segments.manage');
    Route::post('/road-officer/road-segments', [RoadSegmentController::class, 'store'])->name('officer.road-segments.store');
    Route::put('/road-officer/road-segments/{roadSegment}', [RoadSegmentController::class, 'update'])->name('officer.road-segments.update');
    Route::delete('/road-officer/road-segments/{roadSegment}', [RoadSegmentController::class, 'destroy'])->name('officer.road-segments.destroy');
    Route::get('/road-officer/segment-types', [SegmentTypeController::class, 'index'])->name('officer.segment-types.index');
    Route::post('/road-officer/segment-types', [SegmentTypeController::class, 'store'])->name('officer.segment-types.store');
    Route::put('/road-officer/segment-types/{segmentType}', [SegmentTypeController::class, 'update'])->name('officer.segment-types.update');
    Route::delete('/road-officer/segment-types/{segmentType}', [SegmentTypeController::class, 'destroy'])->name('officer.segment-types.destroy');
    Route::get('/road-officer/violation-types', [ViolationTypeController::class, 'index'])->name('officer.violation-types.index');
    Route::post('/road-officer/violation-types', [ViolationTypeController::class, 'store'])->name('officer.violation-types.store');
    Route::put('/road-officer/violation-types/{violationType}', [ViolationTypeController::class, 'update'])->name('officer.violation-types.update');
    Route::delete('/road-officer/violation-types/{violationType}', [ViolationTypeController::class, 'destroy'])->name('officer.violation-types.destroy');
    Route::get('/road-officer/profile', [OfficerProfileController::class, 'show'])->name('officer.profile.show');
    Route::put('/road-officer/profile', [OfficerProfileController::class, 'update'])->name('officer.profile.update');
});
