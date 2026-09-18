@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Detalhes da Mesa #{{ $table->id }}</h1>

        <p><strong>Número:</strong> {{ $table->number }}</p>
        <p><strong>Status:</strong> {{ $table->status }}</p>

        <a href="{{ route('tables.index') }}" class="btn btn-secondary">Voltar</a>
        <a href="{{ route('tables.edit', $table->id) }}" class="btn btn-warning">Editar</a>

        <form action="{{ route('tables.destroy', $table->id) }}" method="POST" style="display:inline-block;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir?')">Excluir</button>
        </form>
    </div>
@endsection
