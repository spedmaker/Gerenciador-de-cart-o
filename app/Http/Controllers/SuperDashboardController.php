<?php

namespace App\Http\Controllers;

use App\Models\Statement;
use App\Models\Transaction;
use Illuminate\Http\Request;

class SuperDashboardController extends Controller
{
    public function index()
    {
        $statements = Statement::orderBy('reference_month')->get();
        $months     = $statements->pluck('reference_month')->values();
        $holders    = Transaction::where('is_payment', false)
                        ->pluck('card_holder')->unique()->sort()->values();

        return view('super-dashboard', compact('months', 'holders'));
    }

    public function data(Request $request)
    {
        $statements = Statement::orderBy('reference_month')->get();
        $refMonths  = $statements->keyBy('id')->map(fn($s) => $s->reference_month);

        $query = Transaction::query();

        if ($request->filled('months')) {
            $selectedIds = $statements->whereIn('reference_month', $request->months)->pluck('id');
            $query->whereIn('statement_id', $selectedIds);
        }

        if ($request->filled('holders')) {
            $query->whereIn('card_holder', $request->holders);
        }

        $allTx = $query->get()->map(fn($t) => [
            'reference_month' => $refMonths[$t->statement_id] ?? null,
            'card_holder'     => $t->card_holder,
            'category'        => $t->category ?? 'Sem categoria',
            'amount'          => abs((float) $t->amount),
            'signed'          => (float) $t->amount,
            'is_payment'      => (bool) $t->is_payment,
            'description'     => $t->description,
            'date'            => $t->date?->format('Y-m-d'),
        ]);

        // Todas as transações sem pagamento (débitos + estornos)
        $gastos = $allTx->where('is_payment', false);

        if ($request->filled('categories')) {
            $gastos = $gastos->whereIn('category', $request->categories);
        }

        // Total líquido: soma dos signed (débitos positivos - estornos negativos)
        $total = round($gastos->sum('signed'), 2);

        // Agrupamentos usando valor signed (líquido por categoria/mês/portador)
        $byCategory = $gastos
            ->groupBy('category')
            ->map(fn($g) => round($g->sum('signed'), 2))
            ->filter(fn($v) => $v != 0)
            ->sortDesc();

        $byMonth = $gastos
            ->groupBy('reference_month')
            ->map(fn($g) => round($g->sum('signed'), 2))
            ->sortKeys();

        $byHolder = $gastos
            ->groupBy('card_holder')
            ->map(fn($g) => round($g->sum('signed'), 2))
            ->sortDesc();

        $byMonthCategory = $gastos
            ->groupBy('reference_month')
            ->map(fn($monthGroup) =>
                $monthGroup->groupBy('category')
                    ->map(fn($g) => round($g->sum('signed'), 2))
                    ->filter(fn($v) => $v != 0)
            )->sortKeys();

        $categories = $allTx->where('is_payment', false)
            ->pluck('category')->unique()->sort()->values();

        return response()->json([
            'by_category'       => $byCategory,
            'by_month'          => $byMonth,
            'by_holder'         => $byHolder,
            'by_month_category' => $byMonthCategory,
            'categories'        => $categories,
            'total'             => $total,
            'count'             => $gastos->where('signed', '>', 0)->count(),
        ]);
    }
}
