@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Editar Mesa #{{ $table->id }}</h1>

        <form action="{{ route('tables.update', $table->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="number">Número da Mesa</label>
                <input type="text" name="number" class="form-control @error('number') is-invalid @enderror" value="{{ old('number', $table->number) }}" required>
                @error('number')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" class="form-control @error('status') is-invalid @enderror" required>
                    <option value="available" {{ $table->status == 'available' ? 'selected' : '' }}>Disponível</option>
                    <option value="reserved" {{ $table->status == 'reserved' ? 'selected' : '' }}>Reservada</option>
                    <option value="busy" {{ $table->status == 'busy' ? 'selected' : '' }}>Ocupada</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">Atualizar</button>
        </form>
    </div>
@endsection
