@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Editar Categoria #{{ $category->id }}</h1>

        <form action="{{ route('category.update', $category->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="name">Editar categoria</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $category->name) }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

           

            <button type="submit" class="btn btn-primary mt-3">Atualizar</button>
        </form>
    </div>
@endsection
