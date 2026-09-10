@extends('layouts.app')

@section('content')
    <div class="container">
        <h1 class="my-4">Detalhes do Estoque</h1>

        <div class="card">
            <div class="card-header">
                Estoque #{{ $stock->id }}
            </div>
            <div class="card-body">
                <h5 class="card-title">Produto: {{ $stock->product->name }}</h5>
                <p class="card-text">Quantidade: {{ $stock->amount }}</p>
                <a href="{{ route('stocks.index') }}" class="btn btn-secondary">Voltar</a>
                <a href="{{ route('stocks.edit', $stock->id) }}" class="btn btn-warning">Editar</a>
            </div>
        </div>
    </div>
@endsection
