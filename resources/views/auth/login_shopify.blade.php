@extends('layouts.shopify')

@section('content')
<div class="container">
    <div class="row justify-content-center">    
        <div class="col-md-6 col-lg-4">
            <div class="card" id="registerCard">
                <div class="card-body">
                    <div class="text-center">
                        <h3>Login</h3>
                        <p class="text-muted">Sign in with Shopify</p>
                    </div>
                    <hr class="mb-4">
                    <form method="GET" action="{{ route('login.shopify') }}" aria-label="{{ __('Register') }}">
                        <div class="form-group">
                            <label for="domain">Shopify</label>
                            <div class="input-group mb-3">
                                <input id="domain" type="text" class="form-control{{ $errors->has('domain') ? ' is-invalid' : '' }}" name="domain" value="{{ old('domain') }}" placeholder="yourshop" aria-describedby="myshopify" required autofocus>
                                <div class="input-group-append">
                                    <span class="input-group-text" id="myshopify">myshopify.com</span>
                                </div>
                            </div>
                            @if ($errors->has('domain')) <span class="alert-danger">{{ $errors->first('domain') }}</span><br> @endif
                            <button type="submit" class="btn btn-primary btn-block">Continue</button>
                        </div>
                    </form>
                </div>
            </div>  
        </div>
    </div>
</div>
@endsection
