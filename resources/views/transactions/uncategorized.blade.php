@extends('layouts.app')
@section('title', 'Pendentes de categoria')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h4 class="mb-0">Pendentes de categoria</h4>
    @if(!$transactions->isEmpty())
    <div class="d-flex gap-2 align-items-center">
        <select class="form-control-sm" id="f-competencia">
            <option value="">Todas as competências</option>
            @foreach($months as $stmtId => $refMonth)
                @if($transactions->has($stmtId))
                    <option value="{{ $stmtId }}">
                        {{ \Carbon\Carbon::parse($refMonth . '-01')->format('m/Y') }}
                    </option>
                @endif
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-secondary" id="clearFilters">Limpar filtros</button>
    </div>
    @endif
</div>

{{-- Barra de ação em massa --}}
@if(!$transactions->isEmpty())
<div class="card p-3 mb-4 d-flex flex-row align-items-center gap-3" id="bulkBar">
    <span class="text-muted small text-nowrap" id="bulkCount">Nenhuma linha selecionada</span>
    <div style="min-width:220px">
        <select id="bulkCategory" disabled>
            <option value="">— escolher categoria —</option>
            @foreach($categories as $cat)
                <option value="{{ $cat }}">{{ $cat }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-primary btn-sm text-nowrap" id="bulkApply" disabled>
        Aplicar aos selecionados
    </button>
</div>
@endif

@if($transactions->isEmpty())
    <div class="alert alert-success">Tudo categorizado! 🎉</div>
@else
    @foreach($transactions as $statementId => $group)
    @php $refMonth = $months[$statementId] ?? $statementId; @endphp
    <div class="competencia-group" data-statement="{{ $statementId }}">
        <h6 class="text-muted mb-2 d-flex align-items-center gap-2">
            <label class="d-flex align-items-center gap-1 mb-0" style="cursor:pointer">
                <input type="checkbox" class="form-check-input mt-0 group-check"
                       data-statement="{{ $statementId }}">
            </label>
            {{ \Carbon\Carbon::parse($refMonth . '-01')->format('m/Y') }}
            <span class="badge bg-secondary group-count">{{ $group->count() }}</span>
        </h6>
        <div class="card mb-4">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:32px"></th>
                        <th>Data</th>
                        <th>Portador</th>
                        <th>Descrição</th>
                        <th>Parcela</th>
                        <th class="text-end">Valor</th>
                        <th>Categoria</th>
                    </tr>
                    <tr style="background:#f1f5f9">
                        <td></td>
                        <td>
                            <input type="text" class="form-control form-control-sm f-date" placeholder="dd/mm">
                        </td>
                        <td>
                            <select class="form-select form-select-sm f-holder">
                                <option value="">Todos</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm f-desc" placeholder="Buscar…">
                        </td>
                        <td></td>
                        <td>
                            <div class="d-flex gap-1">
                                <input type="number" class="form-control form-control-sm f-min" placeholder="Mín" step="0.01">
                                <input type="number" class="form-control form-control-sm f-max" placeholder="Máx" step="0.01">
                            </div>
                        </td>
                        <td></td>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group as $tx)
                    @php
                        $dateStr = $tx->date ? \Carbon\Carbon::parse($tx->date)->format('d/m') : '';
                        $absAmt  = abs($tx->amount);
                    @endphp
                    <tr data-id="{{ $tx->id }}"
                        data-date="{{ $dateStr }}"
                        data-holder="{{ strtolower($tx->card_holder) }}"
                        data-desc="{{ strtolower($tx->description) }}"
                        data-amount="{{ $absAmt }}">
                        <td>
                            <input type="checkbox" class="form-check-input row-check" value="{{ $tx->id }}">
                        </td>
                        <td>{{ $dateStr ?: '–' }}</td>
                        <td class="small text-muted">{{ $tx->card_holder }}</td>
                        <td>{{ $tx->description }}</td>
                        <td>
                            @if($tx->installment)
                                <span class="badge bg-info text-dark">
                                    {{ $tx->installment['current'] }}/{{ $tx->installment['total'] }}
                                </span>
                            @endif
                        </td>
                        <td class="text-end">R$ {{ number_format($absAmt, 2, ',', '.') }}</td>
                        <td>
                            <select class="form-select form-select-sm category-select" data-id="{{ $tx->id }}">
                                <option value="">— escolher —</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}" {{ $tx->category === $cat ? 'selected' : '' }}>
                                        {{ $cat }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
@endif

<script type="module">
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// ---- Popula selects de portador com valores únicos da página ----
const allHolders = [...new Set(
    [...document.querySelectorAll('tr[data-holder]')].map(r => r.dataset.holder)
)].sort();
document.querySelectorAll('.f-holder').forEach(sel => {
    allHolders.forEach(h => {
        const opt = document.createElement('option');
        opt.value = h;
        opt.textContent = h.replace(/\b\w/g, c => c.toUpperCase());
        sel.appendChild(opt);
    });
});

// ---- Estado compartilhado dos filtros ----
const state = { date: '', holder: '', desc: '', min: null, max: null };

function applyFilters() {
    document.querySelectorAll('tr[data-id]').forEach(row => {
        const visible =
            (!state.date   || row.dataset.date.includes(state.date))              &&
            (!state.holder || row.dataset.holder === state.holder)                 &&
            (!state.desc   || row.dataset.desc.includes(state.desc))               &&
            (state.min === null || parseFloat(row.dataset.amount) >= state.min)    &&
            (state.max === null || parseFloat(row.dataset.amount) <= state.max);

        row.style.display = visible ? '' : 'none';
        if (!visible) row.querySelector('.row-check').checked = false;
    });
    updateBulkBar();
}

// Sincroniza valor para todos os inputs da mesma classe e atualiza estado
function syncAndFilter(cls, value) {
    document.querySelectorAll('.' + cls).forEach(el => {
        if (el.value !== value) el.value = value;
    });
    if (cls === 'f-date')   state.date   = value.toLowerCase().trim();
    if (cls === 'f-holder') state.holder = value;
    if (cls === 'f-desc')   state.desc   = value.toLowerCase().trim();
    if (cls === 'f-min')    state.min    = value !== '' ? parseFloat(value) : null;
    if (cls === 'f-max')    state.max    = value !== '' ? parseFloat(value) : null;
    applyFilters();
}

['f-date', 'f-desc'].forEach(cls => {
    document.querySelectorAll('.' + cls).forEach(el =>
        el.addEventListener('input', e => syncAndFilter(cls, e.target.value))
    );
});
['f-holder'].forEach(cls => {
    document.querySelectorAll('.' + cls).forEach(el =>
        el.addEventListener('change', e => syncAndFilter(cls, e.target.value))
    );
});
['f-min', 'f-max'].forEach(cls => {
    document.querySelectorAll('.' + cls).forEach(el =>
        el.addEventListener('input', e => syncAndFilter(cls, e.target.value))
    );
});

// ---- Filtro por competência ----
const fComp = document.getElementById('f-competencia');
let tsComp  = null;
if (fComp) {
    tsComp = new TomSelect(fComp, { allowEmptyOption: true, maxOptions: null, dropdownParent: 'body' });
    fComp.addEventListener('change', function () {
        const val = this.value;
        document.querySelectorAll('.competencia-group').forEach(group => {
            group.style.display = (!val || group.dataset.statement === val) ? '' : 'none';
        });
        document.querySelectorAll('.row-check, .group-check').forEach(cb => {
            const grp = cb.closest('.competencia-group');
            if (grp && grp.style.display === 'none') cb.checked = false;
        });
        updateBulkBar();
    });
}

// ---- Limpar filtros ----
document.getElementById('clearFilters')?.addEventListener('click', () => {
    // Reseta filtros de coluna (competência não é alterada)
    document.querySelectorAll('.f-date, .f-desc').forEach(el => el.value = '');
    document.querySelectorAll('.f-min, .f-max').forEach(el => el.value = '');
    document.querySelectorAll('.f-holder').forEach(el => el.value = '');

    document.querySelectorAll('tr[data-id]').forEach(r => r.style.display = '');

    Object.assign(state, { date: '', holder: '', desc: '', min: null, max: null });

    updateBulkBar();
});

// ---- TomSelect nos selects de categoria ----
document.querySelectorAll('.category-select').forEach(sel => {
    new TomSelect(sel, { allowEmptyOption: true, maxOptions: null, dropdownParent: 'body' });
});

// ---- TomSelect no select de ação em massa ----
const bulkCatEl = document.getElementById('bulkCategory');
const bulkApply = document.getElementById('bulkApply');
const bulkCount = document.getElementById('bulkCount');
let tsBulk = null;
if (bulkCatEl) {
    tsBulk = new TomSelect(bulkCatEl, { allowEmptyOption: true, maxOptions: null, dropdownParent: 'body' });
    bulkCatEl.addEventListener('change', updateBulkBar);
}

// ---- Estado da barra de ação em massa ----
function checkedRows() {
    return [...document.querySelectorAll('.row-check:checked')];
}

function updateBulkBar() {
    const checked = checkedRows();
    const hasCat  = bulkCatEl && bulkCatEl.value;
    bulkCount.textContent = checked.length
        ? `${checked.length} linha(s) selecionada(s)`
        : 'Nenhuma linha selecionada';
    if (bulkCatEl) bulkCatEl.disabled = checked.length === 0;
    if (tsBulk)    tsBulk[checked.length === 0 ? 'disable' : 'enable']();
    if (bulkApply) bulkApply.disabled = checked.length === 0 || !hasCat;
}

// ---- Checkbox de grupo ----
document.querySelectorAll('.group-check').forEach(gc => {
    gc.addEventListener('change', function () {
        const grp = document.querySelector(`.competencia-group[data-statement="${this.dataset.statement}"]`);
        grp?.querySelectorAll('tr[data-id]:not([style*="display: none"]) .row-check')
            .forEach(cb => cb.checked = this.checked);
        updateBulkBar();
    });
});

document.querySelectorAll('.row-check').forEach(cb => {
    cb.addEventListener('change', updateBulkBar);
});

// ---- Remover linha ----
function removeRow(row) {
    row.style.transition = 'opacity .3s';
    row.style.opacity = '0';
    setTimeout(() => {
        row.remove();
        document.querySelectorAll('.competencia-group').forEach(grp => {
            if (!grp.querySelector('tr[data-id]')) grp.style.display = 'none';
        });
        updateBulkBar();
    }, 300);
}

// ---- Salvar categoria ----
function saveCategory(id, cat) {
    return fetch(`/transactions/${id}/category`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ category: cat }),
    }).then(r => { if (!r.ok) throw new Error(); return r.json(); });
}

document.querySelectorAll('.category-select').forEach(sel => {
    sel.addEventListener('change', function () {
        const cat = this.value;
        const row = this.closest('tr');
        if (!cat) return;
        saveCategory(this.dataset.id, cat)
            .then(() => removeRow(row))
            .catch(() => this.classList.add('is-invalid'));
    });
});

// ---- Aplicar em massa ----
if (bulkApply) {
    bulkApply.addEventListener('click', async () => {
        const cat  = bulkCatEl.value;
        const rows = checkedRows().map(cb => cb.closest('tr')).filter(Boolean);
        if (!cat || !rows.length) return;

        bulkApply.disabled    = true;
        bulkApply.textContent = 'Salvando…';

        await Promise.all(rows.map(row =>
            saveCategory(row.dataset.id, cat).then(() => removeRow(row)).catch(() => {})
        ));

        tsBulk.setValue('');
        bulkApply.textContent = 'Aplicar aos selecionados';
        updateBulkBar();
    });
}

updateBulkBar();
</script>
@endsection
