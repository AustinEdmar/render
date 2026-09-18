@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Subcategoria #{{ $subCategory->id }}</h1>

        <p><strong>Nome:</strong> {{ $subCategory->name }}</p>
        <p><strong>Categoria:</strong> {{ $subCategory->category->name }}</p>

        <a href="{{ route('subcategories.edit', $subCategory->id) }}" class="btn btn-warning">Editar</a>

        <form action="{{ route('subcategories.destroy', $subCategory->id) }}" method="POST" style="display:inline-block;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta subcategoria?')">Excluir</button>
        </form>

        <a href="{{ route('subcategories.index') }}" class="btn btn-secondary">Voltar</a>
    </div>
@endsection
