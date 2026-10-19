<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdvanceController;
use App\Http\Controllers\AdvanceReturnController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BillPaymentController;
use App\Http\Controllers\SupplierBillController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('people/{person}/report', [PersonController::class, 'report'])->name('people.report');
Route::resource('people', PersonController::class)->except(['show']);
Route::resource('suppliers', SupplierController::class);
Route::get('bill-payments/create', [BillPaymentController::class, 'create'])->name('bill-payments.create');
Route::post('bill-payments', [BillPaymentController::class, 'store'])->name('bill-payments.store');
Route::get('supplier-bills/{supplierBill}/pay', [BillPaymentController::class, 'create'])->name('supplier-bills.pay');
Route::post('supplier-bills/{supplierBill}/pay', [BillPaymentController::class, 'store'])->name('supplier-bills.pay.store');
Route::resource('supplier-bills', SupplierBillController::class);
Route::delete('bill-payments/{billPayment}', [BillPaymentController::class, 'destroy'])->name('bill-payments.destroy');
Route::resource('accounts', AccountController::class)->except(['show']);
Route::resource('categories', CategoryController::class)->except(['show']);
Route::resource('transactions', TransactionController::class)->except(['show']);
Route::get('transfers/create', [TransferController::class, 'create'])->name('transfers.create');
Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
Route::get('advances/{advance}/settle', [AdvanceController::class, 'settle'])->name('advances.settle');
Route::post('advances/{advance}/settle', [AdvanceController::class, 'settleStore'])->name('advances.settle.store');
Route::resource('advances', AdvanceController::class);
Route::resource('expenses', ExpenseController::class)->except(['show']);
Route::resource('returns', AdvanceReturnController::class)->only(['index', 'create', 'store', 'destroy']);

Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
Route::get('backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download');
Route::delete('backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');
