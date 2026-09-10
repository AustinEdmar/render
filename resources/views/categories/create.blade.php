@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Criar Nova Categoria</h1>

        <form action="{{ route('category.store') }}" method="POST">
            @csrf
            <div class="form-group mb-2">
                <label for="number">categoria</label>
                <input type="text" name="name" class="form-control @error('number') is-invalid @enderror" value="{{ old('number') }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

           

            <button type="submit" class="btn btn-primary">Salvar</button>
        </form>
    </div>
@endsection
