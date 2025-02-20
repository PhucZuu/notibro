@extends('admin.layouts.master')

@section('content')
    <div class="container">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Role Details</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="font-weight-bold">Role Name:</label>
                    <p>{{ $role->name }}</p>
                </div>

                <a href="{{ route('roles.index') }}" class="btn btn-info">Back to List</a>
            </div>
        </div>
    </div>
@endsection