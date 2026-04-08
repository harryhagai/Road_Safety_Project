<?php

use App\Http\Controllers\MapController;
use App\Http\Controllers\OfficerNotificationController;
use App\Http\Controllers\OfficerProfileController;
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
    Route::get('/road-officer/road-segments', [MapController::class, 'lab'])->name('officer.road-segments.index');
    Route::get('/road-officer/road-segments/map-lab', [MapController::class, 'lab'])->name('officer.road-segments.map-lab');
    Route::get('/road-officer/profile', [OfficerProfileController::class, 'show'])->name('officer.profile.show');
    Route::put('/road-officer/profile', [OfficerProfileController::class, 'update'])->name('officer.profile.update');
});
Route::redirect('/e-learning', '/login')->name('e-learning');
