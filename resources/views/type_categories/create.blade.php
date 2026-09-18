@extends('layouts.app')

@section('content')
<div class="container">


    <h1>Adicionar Tipo de Categoria</h1>
    <form action="{{ route('type_categories.store') }}" method="POST">
        @csrf
        <div class="form-group mb-2">
            <label for="name">Nome</label>
            <input type="text" name="name" id="name" class="form-control">
        </div>

        <button type="submit" class="btn btn-success">Salvar</button>
    </form>
    </div>
@endsection
