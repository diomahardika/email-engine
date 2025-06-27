<?php

use App\Http\Controllers\EmailQueueController;
use Illuminate\Support\Facades\Route;

// Route API murni untuk pengiriman email (bebas diakses)
Route::post('/email-queue/sendExcel', [EmailQueueController::class, 'sendEmailsFromExcel']);
Route::post('/email-queue/send', [EmailQueueController::class, 'sendEmails']);