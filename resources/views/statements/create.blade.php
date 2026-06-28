@extends('layouts.app')
@section('title', 'Importar fatura')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card p-4">
            <h5 class="mb-4">Importar faturas PDF</h5>

            @if(session('import_errors'))
                <div class="alert alert-warning">
                    <strong>Alguns arquivos não foram importados:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach(session('import_errors') as $name => $msg)
                            <li><strong>{{ $name }}</strong>: {{ $msg }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('statements.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Arquivos PDF da fatura</label>
                    <input type="file" name="pdfs[]" id="pdfInput" class="form-control" accept=".pdf" multiple required>
                    <div class="form-text">Suporte: Carrefour Mastercard. Máx. 20 MB por arquivo. Múltiplos arquivos permitidos.</div>
                    <ul id="fileList" class="list-unstyled small mt-2 mb-0 text-muted"></ul>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Importar</button>
                    <a href="{{ route('statements.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="module">
document.getElementById('pdfInput').addEventListener('change', function () {
    const list = document.getElementById('fileList');
    list.innerHTML = [...this.files]
        .map(f => `<li class="d-flex align-items-center gap-1"><span class="text-secondary">—</span> ${f.name} <span class="text-muted">(${(f.size / 1024 / 1024).toFixed(1)} MB)</span></li>`)
        .join('');
});
</script>
@endsection
