@extends('admin.layouts.master')

@section('content')
    <div class="container">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Color Details</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="font-weight-bold">Color Name:</label>
                    <p>{{ $color->name }}</p>
                </div>

                <div class="mb-3">
                    <label class="font-weight-bold">Color Code:</label>
                    <p>
                        <span class="color-box" style="display:inline-block; width:50px; height:50px; border-radius:5px; background-color: {{ $color->code }}; border: 1px solid #ddd;"></span>
                        <span class="ml-2">{{ $color->code }}</span>
                    </p>
                </div>

                <a href="{{ route('colors.index') }}" class="btn btn-info">Back to List</a>
            </div>
        </div>
    </div>
@endsection
