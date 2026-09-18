@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center">
        <h1>Lista de Subcategorias</h1>
        <a href="{{ route('sub_categories.create') }}" class="btn btn-primary">Adicionar Subcategoria</a>
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
               
                <th>Categoria</th>
                <th>Tipo de Categoria</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            @foreach($subCategories as $subCategory)
            <tr>
                <td>{{ $subCategory->id }}</td>
                
                <td>{{ $subCategory->category->name }}</td>
                <td>{{ $subCategory->typeCategory->name }}</td>
                <td>
                    <a href="{{ route('sub_categories.edit', $subCategory) }}" class="btn btn-warning btn-sm">Editar</a>
                    <form action="{{ route('sub_categories.destroy', $subCategory) }}" method="POST" class="d-inline">
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
