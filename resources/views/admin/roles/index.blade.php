@extends('admin.layouts.master')

@section('content')
    <div class="container-fluid">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Role List</h6>
                <a href="{{ route('roles.create') }}" class="btn btn-primary ml-2">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Role Name</th>          
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $key => $role)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $role->name }}</td>
                                    <td>
                                        <!-- Nút Chi tiết -->
                                        <a href="{{ route('roles.show', $role->id) }}" class="btn btn-primary">
                                            <i class="fas fa-info-circle"></i>
                                        </a>

                                        <!-- Nút Chỉnh sửa -->
                                        <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        @if ($role->trashed())
                                            <!-- Nếu vai trò đã bị xóa mềm, hiển thị nút khôi phục -->
                                            <form action="{{ route('roles.restore', $role->id) }}" method="POST" style="display:inline-block;"
                                                  onsubmit="return confirm('Are you sure you want to restore this Role?');">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-success" type="submit">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>

                                        @else
                                            <!-- Nếu vai trò chưa bị xóa mềm, hiển thị nút xóa mềm -->
                                            <form action="{{ route('roles.destroy', $role->id) }}" method="POST" style="display:inline-block;"
                                                  onsubmit="return confirm('Are you sure you want to hide this Role?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-warning" type="submit">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                            </form>
                                        @endif

                                         <!-- Nút Xóa cứng -->
                                         <form action="{{ route('roles.forceDelete', $role->id) }}" method="POST" style="display:inline-block;"
                                            onsubmit="return confirm('Are you sure you want to delete this Role?');">
                                          @csrf
                                          @method('DELETE')
                                          <button class="btn btn-danger" type="submit"
                                                  style="border:none; background-color:red; padding:7px; border-radius:5px;">
                                              <i class="fas fa-trash" style="color:black; font-size:20px;"></i>
                                          </button>
                                      </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('#dataTable').DataTable();
        });
    </script>
@endsection
