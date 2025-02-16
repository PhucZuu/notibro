@extends('admin.layouts.master')

@section('content')
    <div class="container">
        <label for="" class="text-info" style="font-size: 30px">Edit  Color</label>
        

        <div class="">
            <form action="" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT') 

                <div class="mb-3">
                    <label for=" Color_id" class="form-label"> Color  Color:</label>
                    <select class="form-control" id=" Color_id" name=" Color_id">
                        <option value="">Select a  Color</option>
                     
                            <option value="">
                             
                            </option>
                   
                    </select>
                </div>

                <div class="mb-3">
                    <label for=" Color_name" class="form-label"> Color Name:</label>
                    <input type="text" class="form-control" id=" Color_name" name=" Color_name" placeholder="Enter Color name" value="">
                </div>


                <button type="submit" class="btn btn-primary">Update  Color</button>
            </form>

            <a href="" class="btn btn-info mt-3">Back to List</a>
        </div>
    </div>
@endsection
