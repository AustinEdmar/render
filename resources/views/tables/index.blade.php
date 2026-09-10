@extends('layouts.app')

@section('content')
    <div class="container">
        <h1 class="my-4 text-center">Mesas</h1>

        <div class="d-flex justify-content-between mb-3">
            <a href="{{ route('tables.create') }}" class="btn btn-primary">Adicionar Nova Mesa</a>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
        </div>

        <table class="table table-striped table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>Número</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tables as $table)
                    <tr>
                        <td>{{ $table->id }}</td>
                        <td>{{ $table->number }}</td>
                        <td>
                            <span class=" text-black
                                {{ $table->status == 'Disponível' ? 'badge-success' : 'badge-danger' }}">
                                {{ $table->status }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('tables.show', $table->id) }}" class="btn btn-info btn-sm">Ver</a>
                            <a href="{{ route('tables.edit', $table->id) }}" class="btn btn-warning btn-sm">Editar</a>
                            <form action="{{ route('tables.destroy', $table->id) }}" method="POST" style="display:inline-block;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza?')">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($tables->isEmpty())
            <div class="alert alert-warning text-center">
                Nenhuma mesa encontrada.
            </div>
        @endif
    </div>
@endsection
