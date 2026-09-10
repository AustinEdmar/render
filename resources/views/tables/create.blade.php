@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Criar Nova Mesa</h1>

        <form action="{{ route('tables.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="number">Número da Mesa</label>
                <input type="text" name="number" class="form-control @error('number') is-invalid @enderror" value="{{ old('number') }}" required>
                @error('number')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" class="form-control @error('status') is-invalid @enderror" required>
                    <option value="available">Disponível</option>
                    <option value="reserved">Reservada</option>
                    <option value="busy">Ocupada</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">Salvar</button>
        </form>
    </div>
@endsection
