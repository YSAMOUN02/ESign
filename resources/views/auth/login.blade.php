@extends('layouts.app')
@section('title', 'Log in')
@section('container-class', 'narrow')
@section('content')
<div class="card" style="margin-top:40px">
    <h1>Welcome back</h1>
    <p class="muted">Log in to send and manage documents.</p>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus></div>
        <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required></div>
        <div class="field"><label class="check"><input type="checkbox" name="remember" value="1"> Remember me</label></div>
        <button class="btn btn-primary btn-block">Log in</button>
    </form>
    <p class="small muted" style="margin-bottom:0">No account? <a href="{{ route('register') }}">Create one</a></p>
</div>
@endsection
