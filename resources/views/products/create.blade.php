@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <h1>Adicionar Produto</h1>
    
    <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label for="name">Nome</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        
        <div class="form-group mt-3">
            <label for="description">Descrição</label>
            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
        </div>
        
        <div class="form-group mt-3">
            <label for="price">Preço</label>
            <input type="number" class="form-control" id="price" name="price" step="0.01" required>
        </div>
        
        <div class="form-group mt-3">
    <label for="sub_category_id">Subcategoria</label>
    <select class="form-control" id="sub_category_id" name="sub_category_id">
        <option value="">Selecione uma subcategoria</option>
        @foreach($categories as $category)
            <optgroup label="{{ $category->name }}">
                @foreach($category->subCategories as $subCategory)
                    <option value="{{ $subCategory->id }}">
                        {{ $subCategory->name }} (Tipo: {{ $subCategory->typeCategory->name }})
                    </option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
</div>

        
        <div class="form-group mt-3">
            <label for="image">Imagem do Produto</label>
            <input type="file" class="form-control-file" id="image" name="image">
        </div>
        
        <button type="submit" class="btn btn-primary mt-3">Salvar Produto</button>
    </form>
</div>
@endsection
