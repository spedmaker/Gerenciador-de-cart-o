@extends('layouts.app')
@section('title', 'Super Dashboard')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h4 class="mb-0">Super Dashboard</h4>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <select id="f-chart-type" class="form-select form-select-sm" style="width:160px">
            <option value="bar">Barras</option>
            <option value="line">Linha</option>
            <option value="area">Área</option>
            <option value="donut">Pizza / Donut</option>
        </select>
        <select id="f-group-by" class="form-select form-select-sm" style="width:160px">
            <option value="category">Por categoria</option>
            <option value="month">Por mês</option>
            <option value="holder">Por portador</option>
            <option value="month_category">Mês × Categoria</option>
        </select>
        <button class="btn btn-sm btn-outline-secondary" id="btnReset">Limpar filtros</button>
    </div>
</div>

{{-- Filtros --}}
<div class="card mb-4 p-3">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Competências</label>
            <select id="f-months" class="form-select form-select-sm" multiple>
                @foreach($months as $m)
                    <option value="{{ $m }}">{{ \Carbon\Carbon::parse($m.'-01')->format('m/Y') }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Portadores</label>
            <select id="f-holders" class="form-select form-select-sm" multiple>
                @foreach($holders as $h)
                    <option value="{{ $h }}">{{ $h }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Categorias</label>
            <select id="f-categories" class="form-select form-select-sm" multiple>
                {{-- populado via JS após o primeiro fetch --}}
            </select>
        </div>
    </div>
</div>

{{-- KPIs --}}
<div class="row g-3 mb-4" id="kpiRow">
    <div class="col-6 col-md-3">
        <div class="card p-3 text-center">
            <div class="small text-muted">Total do período</div>
            <div class="fs-4 fw-bold text-danger" id="kpiTotal">–</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 text-center">
            <div class="small text-muted">Lançamentos</div>
            <div class="fs-4 fw-bold" id="kpiCount">–</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 text-center">
            <div class="small text-muted">Maior categoria</div>
            <div class="fs-6 fw-bold text-truncate" id="kpiTopCat">–</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 text-center">
            <div class="small text-muted">Ticket médio</div>
            <div class="fs-4 fw-bold" id="kpiAvg">–</div>
        </div>
    </div>
</div>

{{-- Gráfico principal --}}
<div class="card p-3 mb-4">
    <div id="mainChart" style="min-height:380px"></div>
</div>

<script type="module">
const API   = '{{ route('super-dashboard.data') }}';
const CSRF  = document.querySelector('meta[name="csrf-token"]').content;

// TomSelects dos filtros
const tsMonths     = new TomSelect('#f-months',     { plugins: ['remove_button'], allowEmptyOption: true, closeAfterSelect: false });
const tsHolders    = new TomSelect('#f-holders',    { plugins: ['remove_button'], allowEmptyOption: true, closeAfterSelect: false });
const tsCategories = new TomSelect('#f-categories', { plugins: ['remove_button'], allowEmptyOption: true, closeAfterSelect: false });

let chart   = null;
let loading = false;

const formatarReal = v => 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
const toArr = v => Array.isArray(v) ? v : (v ? [v] : []);

function buildOptions(type, labels, series) {
    const base = {
        chart:    { type, height: 380, toolbar: { show: true }, animations: { enabled: true } },
        colors:   ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#f97316','#14b8a6','#84cc16','#a855f7','#0ea5e9'],
        tooltip:  { y: { formatter: formatarReal } },
        noData:   { text: 'Sem dados para o filtro selecionado' },
    };

    if (type === 'donut') {
        return { ...base, labels, series };
    }

    if (Array.isArray(series[0]?.data)) {
        // multi-series (mês × categoria)
        return {
            ...base,
            series,
            xaxis: { categories: labels },
            yaxis: { labels: { formatter: formatarReal } },
            legend: { position: 'top' },
            plotOptions: { bar: { columnWidth: '60%' } },
        };
    }

    return {
        ...base,
        series: [{ name: 'Total', data: series }],
        xaxis:  { categories: labels },
        yaxis:  { labels: { formatter: formatarReal } },
        plotOptions: { bar: { columnWidth: '55%', distributed: true } },
        legend: { show: false },
    };
}

function renderChart(type, labels, series) {
    const options = buildOptions(type, labels, series);
    if (chart) {
        chart.destroy();
    }
    chart = new ApexCharts(document.getElementById('mainChart'), options);
    chart.render();
}

async function load(refreshCategories = false) {
    if (loading) return;
    loading = true;

    try {
        const months     = toArr(tsMonths.getValue());
        const holders    = toArr(tsHolders.getValue());
        const categories = toArr(tsCategories.getValue());
        const groupBy    = document.getElementById('f-group-by').value;
        const chartType  = document.getElementById('f-chart-type').value;

        const params = new URLSearchParams();
        months.forEach(m     => params.append('months[]', m));
        holders.forEach(h    => params.append('holders[]', h));
        categories.forEach(c => params.append('categories[]', c));

        const res = await fetch(`${API}?${params}`);
        if (!res.ok) throw new Error(`Erro HTTP ${res.status}`);
        const data = await res.json();

        // KPIs
        document.getElementById('kpiTotal').textContent = formatarReal(data.total);
        document.getElementById('kpiCount').textContent = data.count;
        document.getElementById('kpiAvg').textContent   = data.count > 0 ? formatarReal(data.total / data.count) : '–';

        const topCat = Object.entries(data.by_category)[0];
        document.getElementById('kpiTopCat').textContent = topCat ? `${topCat[0]} (${formatarReal(topCat[1])})` : '–';

        // Atualiza opções de categoria apenas quando mês/portador mudam (não quando categoria muda)
        if (refreshCategories) {
            tsCategories.clear(true);
            tsCategories.clearOptions();
            data.categories.forEach(cat => tsCategories.addOption({ value: cat, text: cat }));
            tsCategories.refreshOptions(false);
        }

        // Monta séries conforme agrupamento
        let labels, series;

        if (groupBy === 'category') {
            labels = Object.keys(data.by_category);
            series = Object.values(data.by_category);
        } else if (groupBy === 'month') {
            labels = Object.keys(data.by_month).map(m => {
                const [y, mo] = m.split('-');
                return `${mo}/${y}`;
            });
            series = Object.values(data.by_month);
        } else if (groupBy === 'holder') {
            labels = Object.keys(data.by_holder);
            series = Object.values(data.by_holder);
        } else if (groupBy === 'month_category') {
            const monthKeys = Object.keys(data.by_month_category);
            labels = monthKeys.map(m => { const [y, mo] = m.split('-'); return `${mo}/${y}`; });

            const allCats = [...new Set(
                monthKeys.flatMap(m => Object.keys(data.by_month_category[m] || {}))
            )];

            series = allCats.map(cat => ({
                name: cat,
                data: monthKeys.map(m => data.by_month_category[m]?.[cat] ?? 0),
            }));
        }

        const type = (groupBy === 'month_category' && chartType === 'donut') ? 'bar' : chartType;
        renderChart(type, labels, series);

    } catch (err) {
        console.error('SuperDashboard load error:', err);
        document.getElementById('mainChart').innerHTML =
            `<div class="alert alert-danger m-3">Erro ao carregar dados: ${err.message}</div>`;
    } finally {
        loading = false;
    }
}

document.getElementById('btnReset').addEventListener('click', () => {
    tsMonths.setValue([]);
    tsHolders.setValue([]);
    tsCategories.clear(true);
    tsCategories.clearOptions();
});

// Meses e portadores: atualiza categorias disponíveis ao mudar
tsMonths.on('change',  () => load(true));
tsHolders.on('change', () => load(true));
// Categoria: só filtra, não toca nas opções disponíveis
tsCategories.on('change', () => load(false));
document.getElementById('f-chart-type').addEventListener('change', () => load(false));
document.getElementById('f-group-by').addEventListener('change',   () => load(false));

// Carga inicial
load();
</script>

@endsection
