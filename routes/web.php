<?php

use Illuminate\Support\Facades\Route;
use Veekthoven\CashierBachs\Http\Controllers\WebhookController;

Route::post('webhook', [WebhookController::class, 'handleWebhook'])->name('webhook');
