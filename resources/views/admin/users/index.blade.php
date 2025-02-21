@extends('admin.layouts.master')

@section('content')
    <div class="container-fluid">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">User List</h6>
                <a href="{{ route('admin.users.index') }}" class="btn btn-primary ml-2">
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
                                <th>ID</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $key => $user)
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->trashed() ? 'Locked' : 'Active' }}</td>
                                    <td>
                                        <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-info btn-sm">Detail</a>
                                        @if ($user->trashed())
                                            <form action="{{ route('admin.users.unlock', $user->id) }}" method="POST" style="display:inline-block;"
                                                  onsubmit="return confirm('Are you sure you want to unlock this user?');">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-success" type="submit">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('admin.users.ban', $user->id) }}" method="POST" style="display:inline-block;"
                                                  onsubmit="return confirm('Are you sure you want to lock this user?');">
                                                @csrf
                                                <button class="btn btn-warning btn-sm">Ban</button>
                                            </form>

                                            <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" style="display:inline-block;"
                                                  onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-warning" type="submit">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                            </form>
                                        @endif

                                        <!-- Nút Xóa cứng -->
                                        <form action="{{ route('admin.users.forceDelete', $user->id) }}" method="POST" style="display:inline-block;"
                                              onsubmit="return confirm('Are you sure you want to delete this User?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger" type="submit" style="border:none; background-color:red; padding:7px; border-radius:5px;">
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
