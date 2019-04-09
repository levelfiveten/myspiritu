<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Level 510 Shopify Public App') }}</title>

    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
    <!-- <script src="{{ secure_asset('js/app.js') }}" defer></script> -->

    <!-- Fonts -->
    <!-- <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet" type="text/css"> -->

    <!-- Styles -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    <!-- <link href="{{ secure_asset('css/app.css') }}" rel="stylesheet"> -->
    <style>
        #registerCard {
            top: 50%;
        }
        .app-connect-success, .app-connect-danger {
            margin-top: 60px;
            margin-bottom: 60px;
        }
        .app-connect-success > span, .app-connect-danger > span {
            font-size: 1.4em;
        }
    </style>
</head>
<body>
    <div id="app">
        <main class="py-4">
            @if (session('status-success'))
                <div class="alert alert-success app-connect-success container">
                    {{ session('status-success') }}
                    <button type="button" data-dismiss="alert" aria-hidden="true" class="close">&times;</button>
                </div>
            @endif
            @if (session('status-error'))
                <div class="alert alert-danger app-connect-danger container">
                    {{ session('status-error') }}
                    <button type="button" data-dismiss="alert" aria-hidden="true" class="close">&times;</button>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
