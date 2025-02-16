@extends('admin.layouts.master')

@section('content')
    <div class="container-fluid">

        <!-- Page Heading -->


        <!-- DataTales Example -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex align-items-center">
                <h6 class="m-0 font-weight-bold text-primary"> Color List</h6>
                <a href="" class="btn btn-primary ml-2">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th> Color Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tfoot>
                            <tr>
                                <th>No</th>
                                <th> Color Name</th>
                                <th>Actions</th>
                            </tr>
                        </tfoot>

                        <tbody>
                        
                                <tr>
                                    <td>id</td>
                                    <td>name</td>
                                    <td>
                                        <a href=""
                                            class="btn btn-primary">
                                            <i class="fas fa-info-circle"></i> <!-- Icon for 'Details' -->
                                        </a>

                                        <a href=""
                                            class="btn btn-warning">
                                            <i class="fas fa-edit"></i> <!-- Icon for 'Edit' -->
                                        </a>

                                        {{-- @if ($Color->trashed()) --}}
                                    
                                            <form action=""
                                                method="POST" style="display:inline-block;"
                                                onsubmit="return confirm('Are you sure you want to restore this  Color ❓');">
                                                @csrf
                                                @method('PATCH') <!-- Use PATCH for restore -->
                                                <button class="btn btn-danger" type="submit"
                                                style="border:none; background-color:#ffcccc; padding:7px; border-radius:5px;">
                                                <i class="fas fa-eye-slash" style="color:brown; font-size:20px;"></i>
                                                <!-- Icon for hiding  Color -->
                                            </button>
                                            </form>
                                        {{-- @else --}}
                                   
                                            <form action=""
                                                method="POST" style="display:inline-block;"
                                                onsubmit="return confirm('Are you sure you want to hide this  Color ❓');">
                                                @csrf
                                                @method('DELETE') <!-- Use DELETE -->
                                                <button class="btn btn-success" type="submit"
                                                    style="border:none; background-color:#ccffcc; padding:7px; border-radius:5px;">
                                                    <i class="fas fa-eye" style="color:green; font-size:20px;"></i>
                                                    <!-- Icon for restoring  Color -->
                                                </button>
                                            </form>
                                        {{-- @endif --}}

                                        <form action=""
                                            method="POST" style="display:inline-block;"
                                            onsubmit="return confirm('Are you sure you want to delete this  Color ❓');">
                                            @csrf
                                            @method('DELETE') <!-- Use DELETE -->
                                            <button class="btn btn-danger" type="submit"
                                                style="border:none; background-color:red; padding:7px; border-radius:5px;">
                                                <i class="fas fa-trash" style="color:black; font-size:20px;"></i>
                                                <!-- Icon for deleting -->
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                        </tbody>
                    </table>
                    {{-- Pagination --}}
                    
                </div>
            </div>
        </div>

    </div>
@endsection
