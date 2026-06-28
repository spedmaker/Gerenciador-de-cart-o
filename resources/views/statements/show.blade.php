@extends('layouts.app')
@section('title', 'Fatura ' . ($statement->reference_month ?? ''))

@section('content')

{{-- Toast --}}
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100">
    <div id="saveToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">
            {{ \Carbon\Carbon::parse(($statement->reference_month ?? '2000-01') . '-01')->translatedFormat('F Y') }}
        </h4>
        <span class="text-muted small">{{ $statement->bank_label }}</span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="text-muted small" id="txCount"></span>
        <button class="btn btn-sm btn-outline-secondary" id="clearFilters">Limpar filtros</button>
        <form method="POST" action="{{ route('statements.reapply-rules', $statement->id) }}">
            @csrf
            <button class="btn btn-sm btn-outline-warning" title="Recategoriza lançamentos sem categoria">
                Reaplicar regras
            </button>
        </form>
        <a href="{{ route('statements.index') }}" class="btn btn-sm btn-outline-secondary">← Voltar</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
    <table class="table table-hover table-sm mb-0" id="txTable">
        <thead class="table-light">
            {{-- Títulos --}}
            <tr>
                <th>Data</th>
                <th>Portador</th>
                <th>Descrição</th>
                <th>Parcela</th>
                <th>Categoria</th>
                <th class="text-end">Valor (R$)</th>
            </tr>
            {{-- Filtros por coluna --}}
            <tr id="filterRow" style="background:#f1f5f9">
                <td>
                    <input type="text" class="form-control form-control-sm col-filter"
                           id="f-date" placeholder="dd/mm" style="min-width:70px">
                </td>
                <td>
                    <select class="form-select form-select-sm col-filter" id="f-holder">
                        <option value="">Todos</option>
                        @foreach($holders as $h)
                            <option value="{{ strtolower($h) }}">{{ $h }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm col-filter"
                           id="f-desc" placeholder="Buscar…">
                </td>
                <td>
                    <select class="form-select form-select-sm col-filter" id="f-installment">
                        <option value="">Todos</option>
                        <option value="sim">Com parcela</option>
                        <option value="nao">Sem parcela</option>
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm col-filter" id="f-category">
                        <option value="">Todas</option>
                        @foreach($categories as $cat)
                            <option value="{{ strtolower($cat) }}">{{ $cat }}</option>
                        @endforeach
                        <option value="pagamento">Pagamento</option>
                    </select>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <input type="number" class="form-control form-control-sm col-filter"
                               id="f-min" placeholder="Mín" step="0.01" style="min-width:60px">
                        <input type="number" class="form-control form-control-sm col-filter"
                               id="f-max" placeholder="Máx" step="0.01" style="min-width:60px">
                    </div>
                </td>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $tx)
            @php
                $dateStr  = $tx->date ? \Carbon\Carbon::parse($tx->date)->format('d/m') : '';
                $catLabel = $tx->is_payment ? 'pagamento' : strtolower($tx->category ?? 'outros');
                $instStr  = $tx->installment ? 'sim' : 'nao';
                $absAmt   = abs($tx->amount);
            @endphp
            <tr class="{{ $tx->is_payment ? 'table-success' : '' }}"
                data-date="{{ $dateStr }}"
                data-holder="{{ strtolower($tx->card_holder) }}"
                data-desc="{{ strtolower($tx->description) }}"
                data-installment="{{ $instStr }}"
                data-category="{{ $catLabel }}"
                data-amount="{{ $absAmt }}">
                <td class="text-nowrap">{{ $dateStr ?: '–' }}</td>
                <td class="small text-muted">{{ $tx->card_holder }}</td>
                <td>{{ $tx->description }}</td>
                <td>
                    @if($tx->installment)
                        <span class="badge bg-info text-dark badge-installment">
                            {{ $tx->installment['current'] }}/{{ $tx->installment['total'] }}
                        </span>
                    @endif
                </td>
                <td>
                    @if(!$tx->is_payment)
                        <select class="form-select form-select-sm category-select"
                                data-id="{{ $tx->id }}"
                                style="min-width:130px">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" {{ $tx->category === $cat ? 'selected' : '' }}>
                                    {{ $cat }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <span class="badge bg-success">Pagamento</span>
                    @endif
                </td>
                <td class="text-end fw-semibold {{ $tx->amount < 0 ? 'text-success' : '' }}">
                    R$ {{ number_format($absAmt, 2, ',', '.') }}
                    @if($tx->amount < 0) <small>(crédito)</small> @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="5" class="fw-semibold text-end">Total visível</td>
                <td class="text-end fw-bold text-danger" id="footerTotal">R$ –</td>
            </tr>
        </tfoot>
    </table>
    </div>
</div>

<script type="module">
const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// ---- Pré-preenche filtros a partir de query params (vindo do dashboard) ----
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('cat'))    document.getElementById('f-category').value = urlParams.get('cat');
if (urlParams.has('holder')) document.getElementById('f-holder').value   = urlParams.get('holder');

// ---- Filtros por coluna ----
const rows    = [...document.querySelectorAll('#txTable tbody tr')];
const fDate   = document.getElementById('f-date');
const fHolder = document.getElementById('f-holder');
const fDesc   = document.getElementById('f-desc');
const fInst   = document.getElementById('f-installment');
const fCat    = document.getElementById('f-category');
const fMin    = document.getElementById('f-min');
const fMax    = document.getElementById('f-max');

function applyFilters() {
    const date   = fDate.value.toLowerCase().trim();
    const holder = fHolder.value;
    const desc   = fDesc.value.toLowerCase().trim();
    const inst   = fInst.value;
    const cat    = fCat.value;
    const min    = fMin.value !== '' ? parseFloat(fMin.value) : null;
    const max    = fMax.value !== '' ? parseFloat(fMax.value) : null;

    let visible = 0;
    let total   = 0;
    rows.forEach(row => {
        const show =
            (!date   || row.dataset.date.includes(date))           &&
            (!holder || row.dataset.holder === holder)              &&
            (!desc   || row.dataset.desc.includes(desc))            &&
            (!inst   || row.dataset.installment === inst)           &&
            (!cat    || row.dataset.category === cat)               &&
            (min === null || parseFloat(row.dataset.amount) >= min) &&
            (max === null || parseFloat(row.dataset.amount) <= max);

        row.style.display = show ? '' : 'none';
        if (show) {
            visible++;
            if (!row.dataset.category || row.dataset.category !== 'pagamento') {
                total += parseFloat(row.dataset.amount) || 0;
            }
        }
    });

    document.getElementById('txCount').textContent = `${visible} de ${rows.length} lançamento(s)`;
    document.getElementById('footerTotal').textContent =
        'R$ ' + total.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

document.querySelectorAll('.col-filter').forEach(el => {
    el.addEventListener('input', applyFilters);
    el.addEventListener('change', applyFilters);
});

document.getElementById('clearFilters').addEventListener('click', () => {
    document.querySelectorAll('.col-filter').forEach(el => el.value = '');
    applyFilters();
});

applyFilters();

// ---- Toast ----
function showToast(msg, ok) {
    const toast = document.getElementById('saveToast');
    document.getElementById('toastMsg').textContent = msg;
    toast.classList.remove('bg-success', 'bg-danger');
    toast.classList.add(ok ? 'bg-success' : 'bg-danger');
    bootstrap.Toast.getOrCreateInstance(toast, { delay: 2000 }).show();
}

// ---- Salvar categoria ----
document.querySelectorAll('.category-select').forEach(sel => {
    sel.addEventListener('change', function () {
        const id  = this.dataset.id;
        const cat = this.value;
        // Atualiza data-category da linha pra filtro refletir imediatamente
        this.closest('tr').dataset.category = cat.toLowerCase();
        applyFilters();

        fetch(`/transactions/${id}/category`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ category: cat }),
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(data => { if (data.ok) showToast('Categoria salva!', true); else throw new Error(); })
        .catch(err => { showToast('Erro ao salvar: ' + err.message, false); });
    });
});
</script>
@endsection
