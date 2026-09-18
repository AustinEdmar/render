@extends('layouts.app')

@section('content')
    <div class="container">
        <h1 class="my-4">Editar Estoque</h1>

        <form action="{{ route('stocks.update', $stock->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="product_id">Produto</label>
                <select name="product_id" id="product_id" class="form-control" required>
                    <option value="">Selecione um produto</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" {{ $product->id == $stock->product_id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>
                @error('product_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="amount">Quantidade</label>
                <input type="number" name="amount" id="amount" class="form-control" value="{{ $stock->amount }}" required>
                @error('amount')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-warning">Atualizar</button>
            <a href="{{ route('stocks.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
@endsection
