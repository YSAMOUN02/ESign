<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <meta name="pdf-worker" content="{{ asset('vendor/pdfjs/pdf.worker.min.js') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body>
<nav class="nav">
    <div class="inner">
        <a href="{{ url('/') }}" class="brand"><span class="logo">✎</span>{{ config('app.name') }}</a>
        <div class="spacer"></div>
        @auth
            <a href="{{ route('documents.index') }}" class="btn btn-sm">Documents</a>
            <a href="{{ route('documents.create') }}" class="btn btn-sm btn-primary">+ New document</a>
            <span class="muted small">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm">Log out</button></form>
        @endauth
    </div>
</nav>
@yield('bar')
<main class="container @yield('container-class')">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
