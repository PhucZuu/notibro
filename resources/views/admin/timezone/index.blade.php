@extends('admin.layouts.app')

@section('title', 'Timezone Management')

@section('content')
    <h1 class="h3 mb-4 text-gray-800">Timezone Management</h1>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">List of Timezones</h6>
        </div>
        <div class="card-body">
            <a href="{{ route('admin.timezones.create') }}" class="btn btn-primary mb-3">Add Timezone</a>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Offset</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($timezones as $timezone)
                        <tr>
                            <td>{{ $timezone->id }}</td>
                            <td>{{ $timezone->name }}</td>
                            <td>{{ $timezone->offset }}</td>
                            <td>
                                <a href="{{ route('admin.timezones.edit', $timezone->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                <form action="{{ route('admin.timezones.destroy', $timezone->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
