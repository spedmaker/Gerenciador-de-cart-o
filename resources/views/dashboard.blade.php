@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h4 class="mb-0">Dashboard</h4>
    <form method="GET" action="{{ route('dashboard') }}">
        <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="" {{ $month === '' ? 'selected' : '' }}>Todos os meses</option>
            @foreach($statements as $s)
                <option value="{{ $s->reference_month }}"
                    {{ $month === $s->reference_month ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::parse($s->reference_month . '-01')->format('m/Y') }}
                </option>
            @endforeach
        </select>
    </form>
</div>

@if ($statements->isEmpty())
    <div class="alert alert-info">
        Nenhuma fatura importada ainda.
        <a href="{{ route('statements.create') }}">Importar agora</a>
    </div>
@else

{{-- Filtro de portador --}}
<div class="mb-4" id="holderFilters">
    <button class="btn btn-sm btn-primary me-1 holder-btn active" data-holder="">Todos</button>
    {{-- populated by JS --}}
</div>

{{-- Cards de resumo --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Total filtrado</div>
            <div class="fs-4 fw-bold text-danger" id="cardTotal">R$ –</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Vencimento</div>
            <div class="fs-5 fw-semibold">
                @if($statement)
                    {{ \Carbon\Carbon::parse($statement->due_date)->format('d/m/Y') }}
                @else
                    <span class="text-muted small">–</span>
                @endif
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Lançamentos</div>
            <div class="fs-5 fw-semibold" id="cardCount">–</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Categorias</div>
            <div class="fs-5 fw-semibold" id="cardCats">–</div>
        </div>
    </div>
</div>

<div class="row g-4">

    {{-- Gráfico de rosquinha --}}
    <div class="col-md-4">
        <div class="card p-3 h-100">
            <h6 class="mb-3">Gastos por categoria</h6>
            <div style="position:relative;max-width:280px;margin:0 auto">
                <canvas id="donutChart"></canvas>
                <div id="donutCenter" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none">
                    <div class="text-muted" style="font-size:.7rem">total</div>
                    <div id="donutTotal" class="fw-bold" style="font-size:1rem">R$ –</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Barras de categoria --}}
    <div class="col-md-4">
        <div class="card p-3 h-100">
            <h6 class="mb-3">Detalhamento</h6>
            <div id="categoryBars">
                <p class="text-muted small">Carregando…</p>
            </div>
        </div>
    </div>

    {{-- Parcelados --}}
    <div class="col-md-4">
        <div class="card p-3 h-100">
            <h6 class="mb-3">Compras parceladas</h6>
            <div id="installmentList">
                <p class="text-muted small">Carregando…</p>
            </div>
        </div>
    </div>

</div>

@endif

<script type="module">
const ALL_TXS    = @json(json_decode($txsJson));
const NET_TOTAL  = {{ $netTotal }};
const ACTIVE_MONTH = '{{ $month }}';

const COLORS = {
    'Mercado':      '#198754',
    'Farmácia':     '#0d6efd',
    'Combustível':  '#fd7e14',
    'Delivery':     '#dc3545',
    'Assinaturas':  '#6f42c1',
    'Transporte':   '#0dcaf0',
    'Lazer':        '#d63384',
    'Parcelado':    '#6610f2',
    'Refeição':     '#ffc107',
    'Sem categoria': '#adb5bd',
};
const PALETTE = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#14b8a6','#f97316','#84cc16'];
function colorFor(cat, idx) { return COLORS[cat] ?? PALETTE[idx % PALETTE.length]; }
function formatarReal(v) { return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

// ---- Build holder filter buttons ----
const holders = [...new Set(ALL_TXS.map(t => t.card_holder))].sort();
const filterBar = document.getElementById('holderFilters');
holders.forEach(h => {
    const btn = document.createElement('button');
    btn.className = 'btn btn-sm btn-outline-secondary me-1 holder-btn';
    btn.dataset.holder = h;
    btn.textContent = h;
    filterBar.appendChild(btn);
});

// ---- State ----
let activeHolder = '';
let chart = null;

function filtered() {
    return activeHolder ? ALL_TXS.filter(t => t.card_holder === activeHolder) : ALL_TXS;
}

function groupByCategory(txs) {
    const map = {};
    txs.forEach(t => {
        const cat = t.category || 'Sem categoria';
        map[cat] = (map[cat] ?? 0) + t.amount;
    });
    return Object.entries(map)
        .map(([cat, amt]) => ({ cat, amt: Math.round(amt * 100) / 100 }))
        .sort((a, b) => b.amt - a.amt);
}

function installmentGroups(txs) {
    const map = {};
    txs.filter(t => t.installment_group_key).forEach(t => {
        const k = t.installment_group_key;
        if (!map[k]) map[k] = { description: t.description, card_holder: t.card_holder, installment: t.installment, amount: 0 };
        map[k].amount += t.amount;
    });
    return Object.values(map).sort((a, b) => b.amount - a.amount);
}

function render() {
    const txs    = filtered();
    const cats   = groupByCategory(txs);
    const total  = activeHolder
        ? cats.reduce((s, c) => s + c.amt, 0) // filtrado por portador: soma dos débitos visíveis
        : NET_TOTAL;                            // sem filtro: total líquido do backend
    const labels = cats.map(c => c.cat);
    const data   = cats.map(c => c.amt);
    const colors = cats.map((c, i) => colorFor(c.cat, i));

    document.getElementById('cardTotal').textContent  = formatarReal(total);
    document.getElementById('cardCount').textContent  = txs.length;
    document.getElementById('cardCats').textContent   = cats.length;
    document.getElementById('donutTotal').textContent = formatarReal(total);

    if (chart) {
        chart.data.labels = labels;
        chart.data.datasets[0].data   = data;
        chart.data.datasets[0].backgroundColor = colors;
        chart.update();
    } else {
        chart = new Chart(document.getElementById('donutChart'), {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{ data, backgroundColor: colors, borderWidth: 2, hoverOffset: 6 }]
            },
            options: {
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: ctx => ` ${ctx.label}: ${formatarReal(ctx.parsed)}` }
                    }
                },
                onClick(e, elements) {
                    if (elements.length) navigate(chart.data.labels[elements[0].index]);
                }
            }
        });
        document.getElementById('donutChart').style.cursor = 'pointer';
    }

    const max = data[0] ?? 1;
    document.getElementById('categoryBars').innerHTML = cats.map((c, i) => `
        <div class="mb-2 cat-bar" data-cat="${c.cat}"
             style="cursor:pointer" title="Ver lançamentos de ${c.cat}">
            <div class="d-flex justify-content-between small mb-1">
                <span class="d-flex align-items-center gap-1">
                    <span style="width:10px;height:10px;border-radius:2px;background:${colors[i]};display:inline-block"></span>
                    ${c.cat}
                </span>
                <span class="fw-semibold">${formatarReal(c.amt)}</span>
            </div>
            <div class="progress" style="height:5px">
                <div class="progress-bar" style="width:${(c.amt/max*100).toFixed(1)}%;background:${colors[i]}"></div>
            </div>
        </div>
    `).join('') || '<p class="text-muted small">Sem dados.</p>';

    document.querySelectorAll('.cat-bar').forEach(el => {
        el.addEventListener('click', () => navigate(el.dataset.cat));
    });

    const groups = installmentGroups(txs);
    document.getElementById('installmentList').innerHTML = groups.length
        ? groups.map(g => `
            <div class="mb-3 border-bottom pb-2">
                <div class="fw-semibold small text-truncate" title="${g.description}">${g.description}</div>
                <div class="text-muted" style="font-size:.8rem">${g.card_holder}</div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="badge bg-secondary" style="font-size:.7rem">
                        ${g.installment?.current ?? '?'}/${g.installment?.total ?? '?'}
                    </span>
                    <span class="small fw-semibold">${formatarReal(g.amount)}</span>
                </div>
            </div>
        `).join('')
        : '<p class="text-muted small">Sem parcelados.</p>';
}

// ---- Navigate to transactions with filters ----
function navigate(cat) {
    const params = new URLSearchParams();
    params.set('cat', cat.toLowerCase());
    if (activeHolder) params.set('holder', activeHolder.toLowerCase());
    if (ACTIVE_MONTH) params.set('competencia', ACTIVE_MONTH);
    window.location.href = '/transactions?' + params.toString();
}

// ---- Holder filter clicks ----
document.querySelectorAll('.holder-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.holder-btn').forEach(b => {
            b.classList.remove('active', 'btn-primary');
            b.classList.add('btn-outline-secondary');
        });
        this.classList.add('active', 'btn-primary');
        this.classList.remove('btn-outline-secondary');
        activeHolder = this.dataset.holder;
        render();
    });
});

if (ALL_TXS.length) render();
</script>

@endsection
