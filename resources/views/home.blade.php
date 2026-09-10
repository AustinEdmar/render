@extends('layouts.app')

@section('content')
<div class="container">
    <header class="d-flex justify-content-between align-items-center mb-4">
        <h1>{{ __('Dashboard') }}</h1>

        <a href="{{ route('logout') }}" 
           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            {{ __('Log Out') }}
        </a>
        <form id="logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
            @csrf
        </form>
    </header>

    @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-4">
                <div class="card-header">Gestao de produtos no Stock</div>
                <div class="card-body">
                <h5 class="card-title">{{ $totalStocks ?? 0 }}</h5>
                <p class="card-text">Gestao de produtos no Stock.</p>
                    <a href="{{ route('stocks.index') }}" class="btn btn-light">Gerenciar Estoques</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card text-white bg-success mb-4">
                <div class="card-header">Gestao de Produtos</div>
                <div class="card-body">
                    <h5 class="card-title">{{ $totalProducts ?? 0 }}</h5>
                    <p class="card-text">Total de produtos cadastrados no sistema.</p>
                    <a href="{{ route('products.index') }}" class="btn btn-light">Ver Produtos</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card text-white bg-danger mb-4">
                <div class="card-header">Gestao de Pratos</div>
                <div class="card-body">
                    <h5 class="card-title">{{ $totalTables ?? 0 }}</h5>
                    <p class="card-text">Total de mesas cadastradas no sistema.</p>
                    <a href="{{ route('tables.index') }}" class="btn btn-light">Ver Mesas</a>
                </div>
            </div>
        </div>
        
    </div>
</div>
@endsection
