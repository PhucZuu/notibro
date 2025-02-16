@extends('admin.layouts.master')

@section('content')
    <div class="container">
        <label for="" class="text-info" style="font-size: 30px">Add New Color</label>



        <div class="">
            <form action="" method="POST">
                @csrf

                <div class="mb-3">
                    <label for=" Color_name" class="form-label"> Color Name:</label>
                    <input type="text" class="form-control" id=" Color_name" name=" Color_name" placeholder="Enter  Color name" value="{{ old(' Color_name') }}">
                </div>

                <button type="submit" class="btn btn-primary">Add New</button>
            </form>

            <a href="" class="btn btn-info mt-3">Back to List</a>
        </div>
    </div>
@endsection