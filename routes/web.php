<?php

use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified'
])->group(function () {
    Route::get('/dashboard', function () {
        return redirect('plans');
    })->name('dashboard');
    Route::get('plans', [PlanController::class,'index'])->name('plans');
    Route::any('checkout', [PlanController::class,'checkout'])->name('checkout');
    Route::any('payment', [PlanController::class,'payment'])->name('payment');
    Route::any('subscription-success', [PlanController::class,'subscriptionSuccess'])->name('subscription-success');
    Route::any('subscription-fail', [PlanController::class,'subscriptionFail'])->name('subscription-fail');
});

Route::any('webhook', [PlanController::class,'webhook'])->name('webhook');