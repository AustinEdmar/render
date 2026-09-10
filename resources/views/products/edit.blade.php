@extends('layouts.app')

@section('title', 'Editar Produto')

@section('content')
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Editar Produto</h1>
        <a href="{{ route('products.index') }}" class="btn btn-secondary">Voltar</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mt-3">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-group mt-3">
            <label for="name">Nome</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $product->name) }}" required>
        </div>

        <div class="form-group mt-3">
            <label for="description">Descrição</label>
            <textarea class="form-control" id="description" name="description" rows="3" required>{{ old('description', $product->description) }}</textarea>
        </div>

        <div class="form-group mt-3">
            <label for="price">Preço</label>
            <input type="number" class="form-control" id="price" name="price" value="{{ old('price', $product->price) }}" required>
        </div>

        <div class="form-group mt-3">
            <label for="sub_category_id">Subcategoria</label>
            <select class="form-control" id="sub_category_id" name="sub_category_id" required>
                <option value="">Selecione uma subcategoria</option>
                @foreach($categories as $category)
                    @foreach($category->subCategories as $subCategory)
                        <option value="{{ $subCategory->id }}" {{ (old('sub_category_id', $product->sub_category_id) == $subCategory->id) ? 'selected' : '' }}>
                            {{ $category->name }} > (Tipo: {{ $subCategory->typeCategory->name }})
                        </option>
                    @endforeach
                @endforeach
            </select>
        </div>

        <div class="form-group mt-3">
            <label for="image">Imagem</label>
            <input type="file" class="form-control" id="image" name="image">
            @if ($product->image_path)
                <div class="mt-2">
                    <img src="{{ Storage::url($product->image_path) }}" alt="{{ $product->name }}" class="img-thumbnail" style="max-width: 150px;">
                </div>
            @endif
        </div>

        <button type="submit" class="btn btn-success mt-3">Salvar</button>
    </form>
</div>
@endsection
