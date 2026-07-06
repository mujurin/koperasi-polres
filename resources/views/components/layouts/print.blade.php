<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
    <style>
        @page {
            margin: 0.5cm;
        }

        body {
            margin: 0;
            padding: 0;
            background: white !important;
            color: black !important;
        }
    </style>
</head>

<body class="bg-white text-black antialiased">
    {{ $slot }}

    @fluxScripts
</body>

</html>