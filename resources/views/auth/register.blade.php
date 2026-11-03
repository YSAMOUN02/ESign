@extends('layouts.app')
@section('title', 'Register')
@section('container-class', 'narrow')
@section('content')
<div class="card" style="margin-top:40px">
    <h1>Create your account</h1>
    <p class="muted">Start collecting legally-binding e-signatures.</p>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="field"><label for="name">Full name</label><input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus></div>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required></div>
        <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required></div>
        <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required></div>
        <button class="btn btn-primary btn-block">Create account</button>
    </form>
    <p class="small muted" style="margin-bottom:0">Already registered? <a href="{{ route('login') }}">Log in</a></p>
</div>
@endsection
