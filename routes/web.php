<?php

use App\Http\Controllers\CategoryRuleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/mensal', [DashboardController::class, 'mensal'])->name('dashboard.mensal');

Route::resource('statements', StatementController::class)
    ->only(['index', 'create', 'store', 'show', 'destroy']);
Route::post('statements/{id}/reapply-rules', [StatementController::class, 'reapplyRules'])
    ->name('statements.reapply-rules');

Route::get('transactions', [TransactionController::class, 'index'])
    ->name('transactions.index');
Route::patch('transactions/{id}/category', [TransactionController::class, 'updateCategory'])
    ->name('transactions.update-category');
Route::get('transactions/uncategorized', [TransactionController::class, 'uncategorized'])
    ->name('transactions.uncategorized');

Route::get('category-rules', [CategoryRuleController::class, 'index'])->name('category-rules.index');
Route::post('category-rules', [CategoryRuleController::class, 'store'])->name('category-rules.store');
Route::patch('category-rules/{id}', [CategoryRuleController::class, 'update'])->name('category-rules.update');
Route::delete('category-rules/{id}', [CategoryRuleController::class, 'destroy'])->name('category-rules.destroy');
Route::post('category-rules/seed', [CategoryRuleController::class, 'seed'])->name('category-rules.seed');
