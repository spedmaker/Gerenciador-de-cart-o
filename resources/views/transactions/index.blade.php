@extends('layouts.app')
@section('title', 'Lançamentos')

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
    <h4 class="mb-0">Lançamentos</h4>
    <div class="d-flex gap-2 align-items-center">
        <span class="text-muted small" id="txCount"></span>
        <select class="form-select form-select-sm" id="f-tipo" style="width:auto">
            <option value="debitos">Débitos</option>
            <option value="creditos">Créditos</option>
            <option value="pagamentos">Pagamentos</option>
            <option value="todos">Todos</option>
        </select>
        <button class="btn btn-sm btn-outline-secondary" id="clearFilters">Limpar filtros</button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="table-scrollable">
    <table class="table table-hover table-sm mb-0" id="txTable">
        <thead class="table-light">
            <tr>
                <th>Competência</th>
                <th>Data</th>
                <th>Portador</th>
                <th>Descrição</th>
                <th>Parcela</th>
                <th>Categoria</th>
                <th class="text-end">Valor (R$)</th>
            </tr>
            <tr id="filterRow" style="background:#f1f5f9">
                <td>
                    <select class="form-select form-select-sm col-filter" id="f-competencia">
                        <option value="">Todas</option>
                        @foreach($months as $m)
                            <option value="{{ $m }}">
                                {{ \Carbon\Carbon::parse($m . '-01')->format('m/Y') }}
                            </option>
                        @endforeach
                    </select>
                </td>
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
                $dateStr    = $tx->date ? \Carbon\Carbon::parse($tx->date)->format('d/m') : '';
                $isCredit   = !$tx->is_payment && $tx->amount < 0;
                $catLabel   = $tx->is_payment ? 'pagamento' : strtolower($tx->category ?? 'outros');
                $instStr    = $tx->installment ? 'sim' : 'nao';
                $absAmt     = abs($tx->amount);
                $refMonth   = $tx->reference_month ?? '';
                $refDisplay = $refMonth ? \Carbon\Carbon::parse($refMonth . '-01')->format('m/Y') : '–';
            @endphp
            <tr class="{{ $tx->is_payment ? 'table-success' : ($isCredit ? 'table-info' : '') }}"
                data-competencia="{{ $refMonth }}"
                data-date="{{ $dateStr }}"
                data-holder="{{ strtolower($tx->card_holder) }}"
                data-desc="{{ strtolower($tx->description) }}"
                data-installment="{{ $instStr }}"
                data-category="{{ $catLabel }}"
                data-amount="{{ $absAmt }}"
                data-signed="{{ $tx->amount }}">
                <td class="text-nowrap text-muted small">{{ $refDisplay }}</td>
                <td class="text-nowrap">{{ $dateStr ?: '–' }}</td>
                <td class="small text-muted">{{ $tx->card_holder }}</td>
                <td>
                    {{ $tx->description }}
                    @if($isCredit)
                        <span class="badge bg-primary ms-1" style="font-size:.65rem">Crédito</span>
                    @endif
                </td>
                <td>
                    @if($tx->installment)
                        <span class="badge bg-info text-dark">
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
                <td class="text-end fw-semibold {{ $isCredit ? 'text-primary' : '' }}">
                    {{ $isCredit ? '−' : '' }} R$ {{ number_format($absAmt, 2, ',', '.') }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="6" class="fw-semibold text-end">Total visível</td>
                <td class="text-end fw-bold text-danger" id="footerTotal">R$ –</td>
            </tr>
        </tfoot>
    </table>
    </div>
</div>

<script type="module">
const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// ---- Refs ----
const rows         = [...document.querySelectorAll('#txTable tbody tr')];
const fComp        = document.getElementById('f-competencia');
const fDate        = document.getElementById('f-date');
const fHolder      = document.getElementById('f-holder');
const fDesc        = document.getElementById('f-desc');
const fInst        = document.getElementById('f-installment');
const fCat         = document.getElementById('f-category');
const fMin         = document.getElementById('f-min');
const fMax         = document.getElementById('f-max');
const fTipo        = document.getElementById('f-tipo');

// ---- Pré-preenche ANTES do TomSelect init (ele lê o valor inicial) ----
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('competencia')) fComp.value   = urlParams.get('competencia');
if (urlParams.has('cat'))         fCat.value    = urlParams.get('cat');
if (urlParams.has('holder'))      fHolder.value = urlParams.get('holder');

// ---- TomSelect nos filtros de coluna ----
const tsOpts = { allowEmptyOption: true, maxOptions: null, dropdownParent: 'body' };
const tsComp   = new TomSelect(fComp,   tsOpts);
const tsHolder = new TomSelect(fHolder, tsOpts);
const tsInst   = new TomSelect(fInst,   tsOpts);
const tsCat    = new TomSelect(fCat,    tsOpts);

// ---- TomSelect nos selects de categoria por linha ----
document.querySelectorAll('.category-select').forEach(sel => {
    new TomSelect(sel, { allowEmptyOption: false, maxOptions: null, dropdownParent: 'body' });
});


// ---- applyFilters lê dos selects nativos (TomSelect os mantém sincronizados) ----
function applyFilters() {
    const comp        = fComp.value;
    const date        = fDate.value.toLowerCase().trim();
    const holder      = fHolder.value;
    const desc        = fDesc.value.toLowerCase().trim();
    const inst        = fInst.value;
    const cat         = fCat.value;
    const min         = fMin.value !== '' ? parseFloat(fMin.value) : null;
    const max         = fMax.value !== '' ? parseFloat(fMax.value) : null;
    const tipo        = fTipo.value;

    let visible = 0, total = 0;
    rows.forEach(row => {
        const isPayment = row.dataset.category === 'pagamento';
        const isCredit  = !isPayment && parseFloat(row.dataset.signed) < 0;
        const tipoOk    = tipo === 'todos'      ||
                          (tipo === 'debitos'    && !isPayment && !isCredit) ||
                          (tipo === 'creditos'   && isCredit)                ||
                          (tipo === 'pagamentos' && isPayment);
        const show =
            tipoOk                                                      &&
            (!comp   || row.dataset.competencia === comp)               &&
            (!date   || row.dataset.date.includes(date))                &&
            (!holder || row.dataset.holder === holder)                  &&
            (!desc   || row.dataset.desc.includes(desc))                &&
            (!inst   || row.dataset.installment === inst)               &&
            (!cat    || row.dataset.category === cat)                   &&
            (min === null || parseFloat(row.dataset.amount) >= min)     &&
            (max === null || parseFloat(row.dataset.amount) <= max);

        row.style.display = show ? '' : 'none';
        if (show) {
            visible++;
            total += parseFloat(row.dataset.signed) || 0;
        }
    });

    document.getElementById('txCount').textContent = `${visible} de ${rows.length} lançamento(s)`;
    document.getElementById('footerTotal').textContent =
        'R$ ' + total.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// TomSelect dispara change no select nativo; inputs de texto usam input
[fComp, fHolder, fInst, fCat, fTipo].forEach(el => el.addEventListener('change', applyFilters));
[fDate, fDesc, fMin, fMax].forEach(el => el.addEventListener('input', applyFilters));

document.getElementById('clearFilters').addEventListener('click', () => {
    fDate.value = '';
    fMin.value  = '';
    fMax.value  = '';
    fDesc.value = '';
    tsComp.setValue('');
    tsHolder.setValue('');
    tsInst.setValue('');
    tsCat.setValue('');
    fTipo.value = 'debitos';
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
        if (!cat) return;
        this.closest('tr').dataset.category = cat.toLowerCase();
        applyFilters();

        fetch(`/transactions/${id}/category`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ category: cat }),
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(data => { if (data.ok) showToast('Categoria salva!', true); else throw new Error(); })
        .catch(err => { showToast('Erro ao salvar: ' + err.message, false); });
    });
});
</script>
@endsection
