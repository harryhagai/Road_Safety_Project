<?php

use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\officer\ContactMessageController as OfficerContactMessageController;
use App\Http\Controllers\officer\OfficerDashboardController;
use App\Http\Controllers\officer\OfficerNotificationController;
use App\Http\Controllers\officer\OfficerProfileController;
use App\Http\Controllers\officer\RoadSegmentController;
use App\Http\Controllers\officer\RoadRuleController;
use App\Http\Controllers\officer\SegmentTypeController;
use App\Http\Controllers\officer\ViolationTypeController;
use App\Http\Controllers\PublicHotspotController;
use Illuminate\Support\Facades\Route;

$routeFiles = [
    'public.php',
    'auth.php',
    'student.php',
    'registrator.php',
    'admin.php',
    'accountant.php',
    'roadofficer.php',
    'teacher.php',
    'head_of_school.php',
    'asset_manager.php',
];

foreach ($routeFiles as $routeFile) {
    $path = __DIR__ . '/' . $routeFile;

    if (is_file($path)) {
        require $path;
    }
}

Route::view('/', 'home')->name('home');
Route::view('/home', 'home');
Route::view('/about', 'about')->name('about');
Route::get('/contact', [ContactMessageController::class, 'create'])->name('contact');
Route::post('/contact', [ContactMessageController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');
Route::view('/departments', 'departments')->name('departments');
Route::view('/developer', 'developer')->name('developer');
Route::get('/hotspots', [PublicHotspotController::class, 'index'])->name('hotspots.index');
Route::redirect('/news-events', '/hotspots')->name('news-events');
Route::get('/road-officer/dashboard', [OfficerDashboardController::class, 'index'])->middleware('auth')->name('officer.dashboard');
Route::get('/roadofficer/dashboard', [OfficerDashboardController::class, 'index'])->middleware('auth')->name('roadofficer.dashboard');
Route::get('/maps/reverse-geocode', [MapController::class, 'reverseGeocode'])
    ->middleware('throttle:30,1')
    ->name('maps.reverse-geocode');
Route::get('/maps/search', [MapController::class, 'search'])
    ->middleware('throttle:60,1')
    ->name('maps.search');

Route::middleware('auth')->group(function () {
    Route::get('/road-officer/notifications', [OfficerNotificationController::class, 'index'])->name('officer.notifications.index');
    Route::get('/road-officer/notifications/dropdown-data', [OfficerNotificationController::class, 'dropdownData'])->name('officer.notifications.dropdown-data');
    Route::post('/road-officer/notifications/mark-all-read', [OfficerNotificationController::class, 'markAllRead'])->name('officer.notifications.mark-all-read');
    Route::get('/road-officer/notifications/{notificationId}', [OfficerNotificationController::class, 'show'])->name('officer.notifications.show');
    Route::get('/road-officer/contact-messages', [OfficerContactMessageController::class, 'index'])->name('officer.contact-messages.index');
    Route::get('/road-officer/contact-messages/{contactMessage}', [OfficerContactMessageController::class, 'show'])->name('officer.contact-messages.show');
    Route::put('/road-officer/contact-messages/{contactMessage}', [OfficerContactMessageController::class, 'update'])->name('officer.contact-messages.update');
    Route::delete('/road-officer/contact-messages/{contactMessage}', [OfficerContactMessageController::class, 'destroy'])->name('officer.contact-messages.destroy');
    Route::get('/road-officer/road-rules', [RoadRuleController::class, 'index'])->name('officer.road-rules.index');
    Route::get('/road-officer/road-rules/data', [RoadRuleController::class, 'data'])->name('officer.road-rules.data');
    Route::post('/road-officer/road-rules', [RoadRuleController::class, 'store'])->name('officer.road-rules.store');
    Route::get('/road-officer/road-segments', [RoadSegmentController::class, 'index'])->name('officer.road-segments.index');
    Route::post('/road-officer/road-segments', [RoadSegmentController::class, 'store'])->name('officer.road-segments.store');
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
Route::redirect('/e-learning', '/login')->name('e-learning');
