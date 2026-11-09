<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Presentation\Http\Controllers\CreateInvoiceController;
use Modules\Invoices\Presentation\Http\Controllers\SendInvoiceController;
use Modules\Invoices\Presentation\Http\Controllers\ShowInvoiceController;

Route::post('/invoices', CreateInvoiceController::class)->name('invoices.store');
Route::get('/invoices/{id}', ShowInvoiceController::class)->whereUuid('id')->name('invoices.show');
Route::post('/invoices/{id}/send', SendInvoiceController::class)->whereUuid('id')->name('invoices.send');
