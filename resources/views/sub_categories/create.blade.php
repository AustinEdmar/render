@extends('layouts.app')

@section('content')
<div class="container">


    <h1>Adicionar Subcategoria</h1>
    <form action="{{ route('sub_categories.store') }}" method="POST">
        @csrf
       

        <div class="form-group mb-2">
            <label for="category_id">Categoria</label>
            <select name="category_id" id="category_id" class="form-control">
                <option value="">Selecione a Categoria</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group mb-2">
            <label for="type_category_id">Tipo de Categoria</label>
            <select name="type_category_id" id="type_category_id" class="form-control">
                <option value="">Selecione o Tipo de Categoria</option>
                @foreach($typeCategories as $typeCategory)
                    <option value="{{ $typeCategory->id }}">{{ $typeCategory->name }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-success">Salvar</button>
    </form>
    </div>
@endsection
