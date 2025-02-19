@extends('admin.layouts.master')

@section('content')
    <div class="container">
        <h2 class="text-info" style="font-size: 30px">Edit Color</h2>

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
            <form action="{{ route('colors.update', $color->id) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- Color Name --}}
                <div class="mb-3">
                    <label for="name" class="form-label">Color Name:</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="Enter color name" 
                           value="{{ old('name', $color->name) }}">
                </div>

                {{-- Color Code --}}
                <div class="mb-3">
                    <label for="code" class="form-label">Color Code (Hex):</label>
                    <input type="color" class="form-control" id="code" name="code" placeholder="Enter color code (e.g., #ff5733)" 
                           value="{{ old('code', $color->code) }}">
                </div>

                <button type="submit" class="btn btn-primary">Update Color</button>
            </form>

            <a href="{{ route('colors.index') }}" class="btn btn-info mt-3">Back to List</a>
        </div>
    </div>
@endsection
