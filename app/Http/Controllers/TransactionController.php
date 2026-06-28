<?php

namespace App\Http\Controllers;

use App\Models\CategoryRule;
use App\Models\Statement;
use App\Models\Transaction;
use App\Services\CategoryService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        // Build a lookup: statement_id → {reference_month, due_date}
        $statementsMap = Statement::orderByDesc('reference_month')
            ->get()
            ->keyBy('id')
            ->map(fn($s) => [
                'reference_month' => $s->reference_month,
                'due_date'        => $s->due_date,
            ]);

        $transactions = Transaction::get()
            ->map(function ($tx) use ($statementsMap) {
                $meta = $statementsMap[$tx->statement_id] ?? ['reference_month' => null, 'due_date' => null];
                $tx->reference_month = $meta['reference_month'];
                return $tx;
            })
            ->sortBy([
                fn($a, $b) => strcmp($a->reference_month ?? '', $b->reference_month ?? ''),
                fn($a, $b) => ($a->date?->timestamp ?? 0) <=> ($b->date?->timestamp ?? 0),
            ])
            ->values();

        $holders = $transactions->filter(fn($t) => !$t->is_payment)
            ->pluck('card_holder')->unique()->sort()->values();

        $months = $statementsMap->pluck('reference_month')
            ->sort()->values();

        $categories = $this->availableCategories();

        return view('transactions.index', compact('transactions', 'holders', 'months', 'categories'));
    }

    public function updateCategory(Request $request, string $id)
    {
        // Accept both JSON body and form-data
        if ($request->isJson()) {
            $request->merge($request->json()->all());
        }

        $request->validate(['category' => 'required|string|max:60']);

        $transaction           = Transaction::findOrFail($id);
        $transaction->category = $request->category;
        $transaction->save();

        return response()->json(['ok' => true, 'category' => $transaction->category]);
    }

    public function uncategorized()
    {
        $months = \App\Models\Statement::orderBy('reference_month')
            ->pluck('reference_month', 'id');

        $transactions = Transaction::where('is_payment', false)
            ->whereIn('category', [null, 'Não categorizado'])
            ->get()
            ->groupBy('statement_id')
            ->sortBy(fn($group, $stmtId) => $months[$stmtId] ?? '')
            ->map(fn($group) => $group->sortBy(fn($tx) => $tx->date?->timestamp ?? 0)->values());

        $categories = $this->availableCategories();

        return view('transactions.uncategorized', compact('transactions', 'months', 'categories'));
    }

    private function availableCategories(): array
    {
        $fromRules = CategoryRule::pluck('category')->unique()->sort()->values()->toArray();
        return array_values(array_unique(array_merge($fromRules, ['Não categorizado'])));
    }
}
