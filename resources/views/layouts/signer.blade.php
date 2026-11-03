<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <meta name="pdf-worker" content="{{ asset('vendor/pdfjs/pdf.worker.min.js') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body>
<nav class="nav"><div class="inner"><span class="brand"><span class="logo">✎</span>{{ config('app.name') }}</span><div class="spacer"></div>@yield('nav')</div></nav>
@yield('bar')
<main class="container @yield('container-class')">
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
