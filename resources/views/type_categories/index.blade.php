@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center">
        <h1>Lista de Tipos de Categoria</h1>
        <a href="{{ route('type_categories.create') }}" class="btn btn-primary">Adicionar Tipo de Categoria</a>
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
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            @foreach($typeCategories as $typeCategory)
            <tr>
                <td>{{ $typeCategory->id }}</td>
                <td>{{ $typeCategory->name }}</td>
                <td>
                    <a href="{{ route('type_categories.edit', $typeCategory) }}" class="btn btn-warning btn-sm">Editar</a>
                    <form action="{{ route('type_categories.destroy', $typeCategory) }}" method="POST" class="d-inline">
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
