@extends('admin.layouts.master')

@section('content')
    <div class="container">
        <h2 class="text-info" style="font-size: 30px">Add New Role</h2>

        {{-- Hiển thị thông báo lỗi nếu có --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-4">
            <form action="{{ route('roles.store') }}" method="POST">
                @csrf

                {{-- Role Name --}}
                <div class="mb-3">
                    <label for="name" class="form-label">Role Name:</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="Enter role name" value="{{ old('name') }}">
                </div>


                <button type="submit" class="btn btn-primary">Add New</button>
            </form>

            <a href="{{ route('roles.index') }}" class="btn btn-info mt-3">Back to List</a>
        </div>
    </div>
@endsection