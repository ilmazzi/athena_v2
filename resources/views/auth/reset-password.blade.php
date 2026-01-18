@extends('layouts.auth', ['title' => 'Reset Password'])

@section('content')
    <div class="d-flex flex-column h-100 p-3">
        <div class="d-flex flex-column flex-grow-1">
            <div class="row h-100">
                <div class="col-xxl-7">
                    <div class="row justify-content-center h-100">
                        <div class="col-lg-6 py-lg-5">
                            <div class="d-flex flex-column h-100 justify-content-center">
                                <div class="auth-logo mb-4">
                                    <a href="{{ route('second', [ 'dashboards' , 'index']) }}" class="logo-dark">
                                        <img src="/images/logo-dark.png" height="24" alt="logo dark">
                                    </a>

                                    <a href="{{ route('second', [ 'dashboards' , 'index']) }}" class="logo-light">
                                        <img src="/images/logo-light.png" height="24" alt="logo light">
                                    </a>
                                </div>

                                <h2 class="fw-bold fs-24">Imposta nuova password</h2>

                                <p class="text-muted mt-1 mb-4">Inserisci una nuova password per il tuo account.</p>

                                <div>
                                    <form class="authentication-form" method="POST" action="{{ route('password.update') }}">
                                        @csrf
                                        <input type="hidden" name="token" value="{{ $request->route('token') }}">
                                        <div class="mb-3">
                                            <label class="form-label" for="email">Email</label>
                                            <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $request->email) }}" required autofocus>
                                            @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="password">Nuova Password</label>
                                            <input type="password" id="password" name="password" class="form-control" required minlength="8">
                                            @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="password_confirmation">Conferma Password</label>
                                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required minlength="8">
                                        </div>
                                        <div class="mb-1 text-center d-grid">
                                            <button class="btn btn-primary" type="submit">Salva nuova password</button>
                                        </div>
                                    </form>
                                </div>

                                <p class="mt-5 text-danger text-center">Torna al <a href="{{ route('login') }}" class="text-dark fw-bold ms-1">Login</a></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-5 d-none d-xxl-flex">
                    <div class="card h-100 mb-0 overflow-hidden">
                        <div class="d-flex flex-column h-100">
                            <img src="/images/small/img-10.jpg" alt="" class="w-100 h-100">
                        </div>
                    </div> <!-- end card -->
                </div>
            </div>
        </div>
    </div>

@endsection
