@extends('layouts.app')
@section('title', 'Faturas')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0">Faturas importadas</h4>
    <a href="{{ route('statements.create') }}" class="btn btn-primary btn-sm">
        + Importar fatura
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('import_errors'))
    <div class="alert alert-warning alert-dismissible fade show py-2" role="alert">
        <strong>Arquivos não importados:</strong>
        <ul class="mb-0 mt-1">
            @foreach(session('import_errors') as $name => $msg)
                <li><strong>{{ $name }}</strong>: {{ $msg }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($statements->isEmpty())
    <div class="alert alert-info">Nenhuma fatura importada ainda.</div>
@else
    <div class="card">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Competência</th>
                    <th>Banco</th>
                    <th>Vencimento</th>
                    <th class="text-end">Débitos</th>
                    <th class="text-end">Tudo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($statements as $s)
                <tr>
                    <td>
                        <a href="{{ route('transactions.index', ['competencia' => $s->reference_month]) }}">
                            {{ \Carbon\Carbon::parse($s->reference_month . '-01')->format('m/Y') }}
                        </a>
                    </td>
                    <td>{{ $s->bank_label }}</td>
                    <td>{{ $s->due_date ? \Carbon\Carbon::parse($s->due_date)->format('d/m/Y') : '–' }}</td>
                    <td class="text-end fw-semibold text-danger">
                        R$ {{ number_format($debitSums[$s->id] ?? 0, 2, ',', '.') }}
                    </td>
                    <td class="text-end text-muted">
                        R$ {{ number_format($s->total_amount ?? 0, 2, ',', '.') }}
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-2 justify-content-end">
                            <form method="POST" action="{{ route('statements.reapply-rules', $s->id) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-warning"
                                        title="Recategoriza lançamentos sem categoria">
                                    Reaplicar regras
                                </button>
                            </form>
                            <form method="POST" action="{{ route('statements.destroy', $s->id) }}"
                                  onsubmit="return confirm('Remover esta fatura?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Remover</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="3" class="fw-semibold text-end">Total acumulado</td>
                    <td class="text-end fw-bold text-danger">
                        R$ {{ number_format($debitSums->sum(), 2, ',', '.') }}
                    </td>
                    <td class="text-end fw-semibold text-muted">
                        R$ {{ number_format($statements->sum('total_amount'), 2, ',', '.') }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
@endsection
