@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center">
        <h1>Lista de Produtos</h1>
        <a href="{{ route('products.create') }}" class="btn btn-primary">Adicionar Produto</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success mt-3">
            {{ session('success') }}
        </div>
    @endif

    <table class="table table-striped mt-4">
        <thead>
            <tr>
                <th>#</th>
                <th>Nome</th>
                <th>Descrição</th>
                <th>Preço</th>
                <th>Categoria</th>
                <th>Tipo</th>
                <th>Imagem</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
            <tr>
                <td>{{ $product->id }}</td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->description }}</td>
                <td>{{ $product->price }} kz</td>
                <td>{{ $product->subCategory->category->name ?? 'Sem Subcategoria' }}</td>
                <td>{{ $product->subCategory->typeCategory->name ?? 'Sem Tipo' }}</td>
                <td>
                    <img src="{{ $product->image_path ? Storage::url($product->image_path) : 'https://via.placeholder.com/100' }}" alt="{{ $product->name }}" class="img-fluid" style="height: 100px; width: auto;">
                </td>
                <td>
                    <a href="{{ route('products.edit', $product) }}" class="btn btn-warning btn-sm">Editar</a>
                    <form action="{{ route('products.destroy', $product) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
