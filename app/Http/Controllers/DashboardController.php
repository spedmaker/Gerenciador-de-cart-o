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

        $txsByStatement = Transaction::whereIn('statement_id', $statements->pluck('id'))
            ->get()
            ->groupBy('statement_id');

        $points = $statements->map(function ($s) use ($txsByStatement) {
            $txs       = $txsByStatement[$s->id] ?? collect();
            $debitos   = $txs->filter(fn($t) => !$t->is_payment && $t->amount > 0);
            $vista     = $debitos->filter(fn($t) => empty($t->installment))->sum('amount');
            $parcelado = $debitos->filter(fn($t) => !empty($t->installment))->sum('amount');
            $estornos  = abs($txs->filter(fn($t) => !$t->is_payment && $t->amount < 0)->sum('amount'));
            $pagamentos = abs($txs->filter(fn($t) => $t->is_payment)->sum('amount'));
            $total     = ($vista + $parcelado) - $estornos - $pagamentos;

            return [
                'label'     => \Carbon\Carbon::parse($s->reference_month . '-01')->format('m/Y'),
                'total'     => round((float) $total, 2),
                'vista'     => round((float) $vista, 2),
                'parcelado' => round((float) $parcelado, 2),
                'estornos'  => round((float) $estornos, 2),
                'month'     => $s->reference_month,
            ];
        })->values();

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

        $txsJson  = '[]';
        $netTotal = 0;

        if (!empty($statementIds)) {
            $refMonths = $statements->keyBy('id')->map(fn($s) => $s->reference_month);

            $all = Transaction::whereIn('statement_id', $statementIds)
                ->where('is_payment', false)
                ->get();

            $debitos  = $all->where('amount', '>', 0);
            $estornos = $all->where('amount', '<', 0)->sum('amount'); // negativo
            $netTotal = round($debitos->sum('amount') + $estornos, 2); // débitos - abs(estornos)

            $txsJson = $debitos->map(fn($t) => [
                'card_holder'           => $t->card_holder,
                'category'              => $t->category ?? 'Sem categoria',
                'amount'                => (float) $t->amount,
                'description'           => $t->description,
                'installment'           => $t->installment,
                'installment_group_key' => $t->installment_group_key,
                'reference_month'       => $refMonths[$t->statement_id] ?? null,
            ])->values()->toJson();
        }

        return view('dashboard', compact('statements', 'statement', 'month', 'txsJson', 'netTotal'));
    }
}
