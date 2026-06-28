@extends('layouts.app')
@section('title', 'Regras de categoria')

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

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0">Regras de categorização</h4>
    <form method="POST" action="{{ route('category-rules.seed') }}">
        @csrf
        <button class="btn btn-sm btn-outline-secondary">Carregar padrões</button>
    </form>
</div>

<div class="row g-4">

    {{-- Formulário de nova regra --}}
    <div class="col-md-4">
        <div class="card p-3">
            <h6 class="mb-3">Nova regra</h6>
            @if($errors->any())
                <div class="alert alert-danger py-2 small">
                    @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
                </div>
            @endif
            <form method="POST" action="{{ route('category-rules.store') }}">
                @csrf
                <div class="mb-2">
                    <label class="form-label small">Padrão (regex) <span class="text-muted">– opcional</span></label>
                    <input type="text" name="pattern" class="form-control form-control-sm"
                           placeholder="MERCADO|SUPERMERCADO" value="{{ old('pattern') }}">
                    <div class="form-text">Deixe vazio para classificação manual.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Categoria</label>
                    <input type="text" name="category" class="form-control form-control-sm"
                           placeholder="Mercado" value="{{ old('category') }}" required>
                </div>
                <button class="btn btn-primary btn-sm">Criar regra</button>
            </form>
        </div>
    </div>

    {{-- Tabela de regras --}}
    <div class="col-md-8">
        <div class="card">
            <table class="table table-sm table-hover mb-0 align-middle" id="rulesTable">
                <thead class="table-light">
                    <tr>
                        <th>Padrão</th>
                        <th>Categoria</th>
                        <th class="text-center" style="width:80px">Lançamentos</th>
                        <th style="width:120px"></th>
                    </tr>
                    <tr style="background:#f1f5f9">
                        <td>
                            <input type="text" id="f-pattern" class="form-control form-control-sm" placeholder="Filtrar…">
                        </td>
                        <td>
                            <input type="text" id="f-category" class="form-control form-control-sm" placeholder="Filtrar…">
                        </td>
                        <td></td>
                        <td></td>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                    @php $txCount = $txCounts[$rule->category] ?? 0; @endphp
                    <tr data-id="{{ $rule->id }}"
                        data-pattern="{{ strtolower($rule->pattern ?? '') }}"
                        data-category="{{ strtolower($rule->category) }}">
                        {{-- View mode --}}
                        <td class="view-mode">
                            @if($rule->pattern)
                                <code class="small">{{ $rule->pattern }}</code>
                            @else
                                <span class="text-muted small fst-italic">manual</span>
                            @endif
                        </td>
                        <td class="view-mode fw-semibold">{{ $rule->category }}</td>
                        <td class="view-mode text-center">
                            @if($txCount > 0)
                                <span class="badge bg-primary rounded-pill">{{ $txCount }}</span>
                            @else
                                <span class="text-muted small">–</span>
                            @endif
                        </td>

                        {{-- Edit mode (hidden) --}}
                        <td class="edit-mode d-none" colspan="3">
                            <div class="d-flex gap-2 align-items-center py-1">
                                <input type="text" class="form-control form-control-sm edit-pattern"
                                       value="{{ $rule->pattern }}" placeholder="Padrão regex (opcional)" style="flex:2">
                                <input type="text" class="form-control form-control-sm edit-category"
                                       value="{{ $rule->category }}" placeholder="Categoria" required style="flex:1">
                                <button class="btn btn-sm btn-success btn-save" title="Salvar">✓</button>
                                <button class="btn btn-sm btn-outline-secondary btn-cancel" title="Cancelar">✕</button>
                            </div>
                        </td>

                        <td class="view-mode text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-sm btn-outline-primary btn-edit py-0 px-2">Editar</button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-danger py-0 px-2 dropdown-toggle"
                                            data-bs-toggle="dropdown" data-count="{{ $txCount }}"
                                            data-category="{{ $rule->category }}">✕</button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        @if($txCount > 0)
                                        <li><h6 class="dropdown-header text-danger">
                                            {{ $txCount }} lançamento(s) com esta categoria
                                        </h6></li>
                                        <li>
                                            <form method="POST" action="{{ route('category-rules.destroy', $rule->id) }}">
                                                @csrf @method('DELETE')
                                                <button class="dropdown-item">
                                                    Remover regra, manter categoria nos lançamentos
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('category-rules.destroy', $rule->id) }}">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="reset_transactions" value="1">
                                                <button class="dropdown-item text-danger">
                                                    Remover regra e deixar lançamentos sem categoria
                                                </button>
                                            </form>
                                        </li>
                                        @else
                                        <li>
                                            <form method="POST" action="{{ route('category-rules.destroy', $rule->id) }}">
                                                @csrf @method('DELETE')
                                                <button class="dropdown-item text-danger">Confirmar exclusão</button>
                                            </form>
                                        </li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted text-center py-3">
                        Nenhuma regra. Clique em "Carregar padrões" para começar.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script type="module">
const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function showToast(msg, ok) {
    const toast = document.getElementById('saveToast');
    document.getElementById('toastMsg').textContent = msg;
    toast.classList.remove('bg-success', 'bg-danger');
    toast.classList.add(ok ? 'bg-success' : 'bg-danger');
    bootstrap.Toast.getOrCreateInstance(toast, { delay: 2000 }).show();
}

function enterEdit(row) {
    row.querySelectorAll('.view-mode').forEach(el => el.classList.add('d-none'));
    row.querySelectorAll('.edit-mode').forEach(el => el.classList.remove('d-none'));
    row.querySelector('.edit-category').focus();
}

function exitEdit(row) {
    row.querySelectorAll('.view-mode').forEach(el => el.classList.remove('d-none'));
    row.querySelectorAll('.edit-mode').forEach(el => el.classList.add('d-none'));
}

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => enterEdit(btn.closest('tr')));
});

document.querySelectorAll('.btn-cancel').forEach(btn => {
    btn.addEventListener('click', () => exitEdit(btn.closest('tr')));
});

document.querySelectorAll('.btn-save').forEach(btn => {
    btn.addEventListener('click', () => {
        const row      = btn.closest('tr');
        const id       = row.dataset.id;
        const pattern  = row.querySelector('.edit-pattern').value.trim();
        const category = row.querySelector('.edit-category').value.trim();

        if (!category) {
            row.querySelector('.edit-category').classList.add('is-invalid');
            return;
        }
        row.querySelector('.edit-category').classList.remove('is-invalid');

        fetch(`/category-rules/${id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ pattern: pattern || null, category }),
        })
        .then(r => r.json().then(d => { if (!r.ok) throw new Error(d.error || 'HTTP ' + r.status); return d; }))
        .then(data => {
            // Update view-mode cells
            const viewCells = row.querySelectorAll('.view-mode td, td.view-mode');
            // easier: update the text of the first two view-mode tds
            const tds = [...row.querySelectorAll('td.view-mode')];
            tds[0].innerHTML = data.pattern
                ? `<code class="small">${escHtml(data.pattern)}</code>`
                : `<span class="text-muted small fst-italic">manual</span>`;
            tds[1].textContent = data.category;

            // Update edit inputs to reflect saved values
            row.querySelector('.edit-pattern').value  = data.pattern ?? '';
            row.querySelector('.edit-category').value = data.category;

            // Update data-* so filters work after inline edit
            row.dataset.pattern  = (data.pattern ?? '').toLowerCase();
            row.dataset.category = data.category.toLowerCase();

            exitEdit(row);
            showToast('Regra salva!', true);
        })
        .catch(err => showToast('Erro ao salvar: ' + err.message, false));
    });
});

// Save on Enter key inside edit inputs
document.querySelectorAll('.edit-pattern, .edit-category').forEach(input => {
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') input.closest('tr').querySelector('.btn-save').click();
        if (e.key === 'Escape') exitEdit(input.closest('tr'));
    });
});

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ---- Filtros de coluna ----
const fPattern  = document.getElementById('f-pattern');
const fCategory = document.getElementById('f-category');

function applyFilters() {
    const pat = fPattern.value.toLowerCase().trim();
    const cat = fCategory.value.toLowerCase().trim();
    document.querySelectorAll('#rulesTable tbody tr[data-id]').forEach(row => {
        const match = (!pat || row.dataset.pattern.includes(pat)) &&
                      (!cat || row.dataset.category.includes(cat));
        row.style.display = match ? '' : 'none';
    });
}

fPattern.addEventListener('input', applyFilters);
fCategory.addEventListener('input', applyFilters);
</script>
@endsection
