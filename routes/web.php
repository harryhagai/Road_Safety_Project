<?php

use App\Http\Controllers\MapController;
use App\Http\Controllers\officer\OfficerNotificationController;
use App\Http\Controllers\officer\OfficerProfileController;
use App\Http\Controllers\officer\RoadSegmentController;
use App\Http\Controllers\officer\RoadRuleController;
use App\Http\Controllers\officer\ViolationTypeController;
use Illuminate\Support\Facades\Route;

$routeFiles = [
    'public.php',
    'auth.php',
    'student.php',
    'registrator.php',
    'admin.php',
    'accountant.php',
    'academic.php',
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
Route::view('/contact', 'contact')->name('contact');
Route::view('/departments', 'departments')->name('departments');
Route::view('/developer', 'developer')->name('developer');
Route::view('/news-events', 'news_events')->name('news-events');
Route::view('/road-officer/dashboard', 'officer.dashboard')->middleware('auth')->name('officer.dashboard');
Route::view('/academic/dashboard', 'officer.dashboard')->middleware('auth')->name('academic.dashboard');
Route::get('/maps/reverse-geocode', [MapController::class, 'reverseGeocode'])
    ->middleware('throttle:30,1')
    ->name('maps.reverse-geocode');

Route::middleware('auth')->group(function () {
    Route::get('/road-officer/notifications', [OfficerNotificationController::class, 'index'])->name('officer.notifications.index');
    Route::get('/road-officer/notifications/dropdown-data', [OfficerNotificationController::class, 'dropdownData'])->name('officer.notifications.dropdown-data');
    Route::post('/road-officer/notifications/mark-all-read', [OfficerNotificationController::class, 'markAllRead'])->name('officer.notifications.mark-all-read');
    Route::get('/road-officer/notifications/{notificationId}', [OfficerNotificationController::class, 'show'])->name('officer.notifications.show');
    Route::get('/road-officer/road-rules', [RoadRuleController::class, 'index'])->name('officer.road-rules.index');
    Route::post('/road-officer/road-rules', [RoadRuleController::class, 'store'])->name('officer.road-rules.store');
    Route::get('/road-officer/road-segments', [RoadSegmentController::class, 'index'])->name('officer.road-segments.index');
    Route::post('/road-officer/road-segments', [RoadSegmentController::class, 'store'])->name('officer.road-segments.store');
    Route::get('/road-officer/violation-types', [ViolationTypeController::class, 'index'])->name('officer.violation-types.index');
    Route::post('/road-officer/violation-types', [ViolationTypeController::class, 'store'])->name('officer.violation-types.store');
    Route::put('/road-officer/violation-types/{violationType}', [ViolationTypeController::class, 'update'])->name('officer.violation-types.update');
    Route::delete('/road-officer/violation-types/{violationType}', [ViolationTypeController::class, 'destroy'])->name('officer.violation-types.destroy');
    Route::get('/road-officer/profile', [OfficerProfileController::class, 'show'])->name('officer.profile.show');
    Route::put('/road-officer/profile', [OfficerProfileController::class, 'update'])->name('officer.profile.update');
});
Route::redirect('/e-learning', '/login')->name('e-learning');
