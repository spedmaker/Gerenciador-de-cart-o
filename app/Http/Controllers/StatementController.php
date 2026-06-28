<?php

namespace App\Http\Controllers;

use App\Models\CategoryRule;
use App\Models\Statement;
use App\Models\Transaction;
use App\Services\CategoryService;
use App\Services\PdfParserManager;
use App\Services\Parsers\CarrefourPdfParser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StatementController extends Controller
{
    public function index()
    {
        $statements = Statement::orderByDesc('reference_month')->get();

        $debitSums = Transaction::where('is_payment', false)
            ->get()
            ->groupBy('statement_id')
            ->map(fn($g) => $g->sum('amount'));

        return view('statements.index', compact('statements', 'debitSums'));
    }

    public function create()
    {
        return view('statements.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'pdfs'   => 'required|array|min:1',
            'pdfs.*' => 'file|mimes:pdf|max:20480',
        ]);

        $parser      = new PdfParserManager([new CarrefourPdfParser()]);
        $categorizer = new CategoryService();
        $imported    = [];
        $errors      = [];

        foreach ($request->file('pdfs') as $file) {
            $name = $file->getClientOriginalName();
            $path = null;

            try {
                $filename = Str::uuid() . '.pdf';
                $path     = $file->storeAs('statements', $filename, 'local');

                $parsed = $parser->parse(storage_path("app/private/{$path}"));
                $meta   = $parsed['meta'];
                $txData = $parsed['transactions'];

                if (Statement::where('reference_month', $meta['reference_month'])->exists()) {
                    $month = \Carbon\Carbon::parse($meta['reference_month'] . '-01')->format('m/Y');
                    throw new \RuntimeException("Fatura {$month} já importada. Remova a existente antes de reimportar.");
                }

                $statement = Statement::create([
                    'bank_label'       => $meta['bank_label'],
                    'reference_month'  => $meta['reference_month'],
                    'due_date'         => $meta['due_date'],
                    'closing_date'     => $meta['closing_date'],
                    'total_amount'     => $meta['total_amount'],
                    'previous_balance' => $meta['previous_balance'],
                    'raw_file'         => $path,
                ]);

                foreach ($txData as $tx) {
                    $tx['statement_id'] = $statement->id;
                    $tx['category']     = $tx['is_payment'] ? null : $categorizer->categorize($tx['description']);
                    Transaction::create($tx);
                }

                $month      = \Carbon\Carbon::parse($meta['reference_month'] . '-01')->format('m/Y');
                $imported[] = "{$name} ({$month}, " . count($txData) . " lançamentos)";

            } catch (\Exception $e) {
                if ($path) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                }
                $errors[$name] = $e->getMessage();
            }
        }

        // Tudo falhou
        if (empty($imported)) {
            $allErrors = collect($errors)->map(fn($msg, $n) => "{$n}: {$msg}")->values()->toArray();
            return back()->withErrors(['pdfs' => $allErrors]);
        }

        // Ao menos um importado
        $success = count($imported) === 1
            ? 'Fatura importada: ' . $imported[0]
            : count($imported) . ' faturas importadas: ' . implode('; ', $imported) . '.';

        if (!empty($errors)) {
            session()->flash('import_errors', $errors);
        }

        return redirect()->route('statements.index')->with('success', $success);
    }

    public function show(string $id)
    {
        $statement = Statement::findOrFail($id);
        return redirect()->route('transactions.index', ['competencia' => $statement->reference_month]);
    }

    public function reapplyRules(string $id)
    {
        $statement   = Statement::findOrFail($id);
        $categorizer = new CategoryService();
        $updated     = 0;

        // Only touch uncategorized transactions — preserve manual edits
        Transaction::where('statement_id', $statement->id)
            ->where('is_payment', false)
            ->whereNull('category')
            ->each(function ($tx) use ($categorizer, &$updated) {
                $newCategory = $categorizer->categorize($tx->description);
                if ($newCategory) {
                    $tx->category = $newCategory;
                    $tx->save();
                    $updated++;
                }
            });

        return redirect()->route('transactions.index', ['competencia' => $statement->reference_month])
            ->with('success', $updated > 0
                ? "{$updated} lançamento(s) recategorizado(s)."
                : "Nenhum lançamento novo para categorizar. Crie regras em Regras para os demais.");
    }

    private function availableCategories(): array
    {
        $fromRules = CategoryRule::pluck('category')->unique()->sort()->values()->toArray();
        return array_values(array_unique(array_merge($fromRules, ['Não categorizado'])));
    }

    public function destroy(string $id)
    {
        $statement = Statement::findOrFail($id);
        Transaction::where('statement_id', $statement->id)->delete();
        $statement->delete();

        return redirect()->route('statements.index')
            ->with('success', 'Fatura removida.');
    }
}
