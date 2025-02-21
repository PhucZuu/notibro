<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div id="wrapper">
        {{-- Sidebar --}}
        @include('admin.layouts.sidebar')

        {{-- Content --}}
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('admin.layouts.topbar')
                <div class="container-fluid">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
</body>
</html>
