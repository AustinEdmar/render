@extends('layouts.app')

@section('content')
    <div class="container">
        <h1 class="my-4">Estoques</h1>

        <a href="{{ route('stocks.create') }}" class="btn btn-primary mb-3">Adicionar Novo Estoque</a>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <table class="table table-striped table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>Produto</th>
                    <th>Quantidade</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stocks as $stock)
                    <tr>
                        <td>{{ $stock->id }}</td>
                        <td>{{ $stock->product->name }}</td>
                        <td>{{ $stock->amount }}</td>
                        <td>
                            <a href="{{ route('stocks.show', $stock->id) }}" class="btn btn-info btn-sm">Ver</a>
                            <a href="{{ route('stocks.edit', $stock->id) }}" class="btn btn-warning btn-sm">Editar</a>
                            <form action="{{ route('stocks.destroy', $stock->id) }}" method="POST" style="display:inline-block;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza?')">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
