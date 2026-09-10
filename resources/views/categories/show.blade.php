@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Detalhes da categoria #{{ $category->id }}</h1>

        <p><strong>Nome:</strong> {{ $category->name }}</p>
     

        <a href="{{ route('category.index') }}" class="btn btn-secondary">Voltar</a>
        <a href="{{ route('category.edit', $category->id) }}" class="btn btn-warning">Editar</a>

        <form action="{{ route('category.destroy', $category->id) }}" method="POST" style="display:inline-block;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir?')">Excluir</button>
        </form>
    </div>
@endsection
