<?php

namespace App\Http\Controllers;

use App\Models\CategoryRule;
use App\Services\CategoryService;
use Illuminate\Http\Request;

class CategoryRuleController extends Controller
{
    public function index()
    {
        $rules = CategoryRule::orderBy('category')->get();

        $txCounts = \App\Models\Transaction::where('is_payment', false)
            ->get()
            ->groupBy('category')
            ->map(fn($g) => $g->count());

        return view('category_rules.index', compact('rules', 'txCounts'));
    }

    private function patternRules(): array
    {
        return ['nullable', 'string', 'max:200', function ($attr, $val, $fail) {
            if (empty($val)) return;
            if (@preg_match('/' . $val . '/i', null) === false) {
                $fail('Padrão regex inválido.');
                return;
            }
            if (@preg_match('/' . $val . '/i', '') === 1) {
                $fail('O padrão não pode corresponder a texto vazio — verifique pipes (|) no início ou fim.');
            }
        }];
    }

    public function store(Request $request)
    {
        $request->validate([
            'pattern'  => $this->patternRules(),
            'category' => 'required|string|max:60',
        ]);

        CategoryRule::create($request->only('pattern', 'category'));

        return back()->with('success', 'Regra criada.');
    }

    public function update(Request $request, string $id)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'pattern'  => $this->patternRules(),
            'category' => 'required|string|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'error' => $validator->errors()->first()], 422);
        }

        $rule = CategoryRule::findOrFail($id);
        $oldCategory = $rule->category;
        $rule->pattern  = $request->pattern ?: null;
        $rule->category = $request->category;
        $rule->save();

        $updated = 0;
        if ($oldCategory !== $rule->category) {
            $updated = \App\Models\Transaction::where('is_payment', false)
                ->where('category', $oldCategory)
                ->update(['category' => $rule->category]);
        }

        return response()->json(['ok' => true, 'pattern' => $rule->pattern, 'category' => $rule->category, 'transactions_updated' => $updated]);
    }

    public function destroy(Request $request, string $id)
    {
        $rule = CategoryRule::findOrFail($id);
        $category = $rule->category;
        $rule->delete();

        if ($request->boolean('reset_transactions')) {
            $count = \App\Models\Transaction::where('is_payment', false)
                ->where('category', $category)
                ->update(['category' => null]);

            return back()->with('success', "Regra removida. {$count} lançamento(s) ficaram sem categoria.");
        }

        return back()->with('success', "Regra \"{$category}\" removida. Lançamentos existentes mantidos.");
    }

    public function seed()
    {
        foreach (CategoryService::defaults() as $rule) {
            CategoryRule::firstOrCreate(['pattern' => $rule['pattern']], $rule);
        }
        return back()->with('success', 'Regras padrão carregadas.');
    }
}
