<?php

namespace App\Http\Controllers;

use App\Models\Statement;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function mensal()
    {
        $statements = Statement::orderBy('reference_month')->get();

        $points = $statements->map(fn($s) => [
            'label'  => \Carbon\Carbon::parse($s->reference_month . '-01')->format('m/Y'),
            'total'  => (float) ($s->total_amount ?? 0),
            'month'  => $s->reference_month,
        ])->values();

        return view('dashboard-mensal', compact('points', 'statements'));
    }

    public function index(Request $request)
    {
        $month = $request->query('month', '');

        $statements = Statement::orderByDesc('reference_month')->get();

        if ($month) {
            $statement = $statements->firstWhere('reference_month', $month);
            $statementIds = $statement ? [$statement->id] : [];
        } else {
            $statement    = null;
            $statementIds = $statements->pluck('id')->toArray();
        }

        $txsJson = '[]';

        if (!empty($statementIds)) {
            $refMonths = $statements->keyBy('id')->map(fn($s) => $s->reference_month);

            $txs = Transaction::whereIn('statement_id', $statementIds)
                ->where('is_payment', false)
                ->get()
                ->map(fn($t) => [
                    'card_holder'           => $t->card_holder,
                    'category'              => $t->category ?? 'Sem categoria',
                    'amount'                => (float) $t->amount,
                    'description'           => $t->description,
                    'installment'           => $t->installment,
                    'installment_group_key' => $t->installment_group_key,
                    'reference_month'       => $refMonths[$t->statement_id] ?? null,
                ]);

            $txsJson = $txs->toJson();
        }

        return view('dashboard', compact('statements', 'statement', 'month', 'txsJson'));
    }
}
