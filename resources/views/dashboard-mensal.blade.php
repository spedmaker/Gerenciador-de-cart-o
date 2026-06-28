@extends('layouts.app')
@section('title', 'Evolução mensal')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0">Evolução mensal</h4>
</div>

@if($statements->isEmpty())
    <div class="alert alert-info">
        Nenhuma fatura importada ainda.
        <a href="{{ route('statements.create') }}">Importar agora</a>
    </div>
@else

{{-- Cards de resumo --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Menor fatura</div>
            <div class="fs-5 fw-bold text-success" id="cardMin">–</div>
            <div class="text-muted" style="font-size:.75rem" id="cardMinMonth"></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Maior fatura</div>
            <div class="fs-5 fw-bold text-danger" id="cardMax">–</div>
            <div class="text-muted" style="font-size:.75rem" id="cardMaxMonth"></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Média mensal</div>
            <div class="fs-5 fw-bold" id="cardAvg">–</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Total acumulado</div>
            <div class="fs-5 fw-bold text-danger" id="cardTotal">–</div>
        </div>
    </div>
</div>

{{-- Gráfico --}}
<div class="card p-4">
    <canvas id="lineChart" style="max-height:380px"></canvas>
</div>

{{-- Tabela resumo --}}
<div class="card mt-4">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>Competência</th>
                <th class="text-end">Total (R$)</th>
                <th class="text-end">Variação</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="summaryTable"></tbody>
    </table>
</div>

@endif

<script type="module">
const POINTS = @json($points);

function fmt(v) {
    return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

if (!POINTS.length) {
    document.getElementById('lineChart')?.closest('.card').remove();
} else {
    const labels = POINTS.map(p => p.label);
    const data   = POINTS.map(p => p.total);
    const totals = data;

    // ---- Cards ----
    const minIdx = data.indexOf(Math.min(...data));
    const maxIdx = data.indexOf(Math.max(...data));
    const avg    = data.reduce((a, b) => a + b, 0) / data.length;
    const total  = data.reduce((a, b) => a + b, 0);

    document.getElementById('cardMin').textContent      = fmt(data[minIdx]);
    document.getElementById('cardMinMonth').textContent = labels[minIdx];
    document.getElementById('cardMax').textContent      = fmt(data[maxIdx]);
    document.getElementById('cardMaxMonth').textContent = labels[maxIdx];
    document.getElementById('cardAvg').textContent      = fmt(avg);
    document.getElementById('cardTotal').textContent    = fmt(total);

    // ---- Tabela ----
    document.getElementById('summaryTable').innerHTML = POINTS.map((p, i) => {
        const prev      = i > 0 ? data[i - 1] : null;
        const diff      = prev !== null ? p.total - prev : null;
        const pct       = prev !== null && prev > 0 ? ((diff / prev) * 100).toFixed(1) : null;
        const up        = diff > 0;
        const varHtml   = diff !== null
            ? `<span class="${up ? 'text-danger' : 'text-success'}">
                ${up ? '▲' : '▼'} ${fmt(Math.abs(diff))} (${pct}%)
               </span>`
            : '<span class="text-muted">–</span>';

        return `<tr>
            <td class="fw-semibold">${p.label}</td>
            <td class="text-end">${fmt(p.total)}</td>
            <td class="text-end small">${varHtml}</td>
            <td class="text-end">
                <a href="/transactions?competencia=${p.month}"
                   class="btn btn-sm btn-outline-secondary py-0 px-2">Ver</a>
            </td>
        </tr>`;
    }).join('');

    // ---- Gráfico de linha ----
    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Total da fatura (R$)',
                data,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220,53,69,.08)',
                borderWidth: 2.5,
                pointBackgroundColor: '#dc3545',
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + fmt(ctx.parsed.y)
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: v => 'R$ ' + v.toLocaleString('pt-BR')
                    }
                }
            },
            onClick(e, elements) {
                if (elements.length) {
                    const idx = elements[0].index;
                    window.location.href = `/transactions?competencia=${POINTS[idx].month}`;
                }
            }
        }
    });

    document.getElementById('lineChart').style.cursor = 'pointer';
}
</script>

@endsection
