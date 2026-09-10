@extends('layouts.app')

@section('content')
    <h1>Editar Tipo de Categoria</h1>
    <form action="{{ route('type_categories.update', $typeCategory->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="name">Nome</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ $typeCategory->name }}">
        </div>

        <button type="submit" class="btn btn-success">Atualizar</button>
    </form>
@endsection
