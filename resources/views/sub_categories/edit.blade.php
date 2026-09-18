@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Editar Subcategoria</h1>
    <form action="{{ route('sub_categories.update', $subCategory->id) }}" method="POST">
        @csrf
        @method('PUT')

       

   

        <div class="form-group mb-2">
            <label for="category_id">Categoria</label>
            <select name="category_id" id="category_id" class="form-control">
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ $subCategory->category_id == $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group mb-2">
            <label for="type_category_id">Tipo de Categoria</label>
            <select name="type_category_id" id="type_category_id" class="form-control">
                @foreach($typeCategories as $typeCategory)
                    <option value="{{ $typeCategory->id }}" {{ $subCategory->type_category_id == $typeCategory->id ? 'selected' : '' }}>
                        {{ $typeCategory->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-success">Atualizar</button>
    </form>
    </div>
@endsection
