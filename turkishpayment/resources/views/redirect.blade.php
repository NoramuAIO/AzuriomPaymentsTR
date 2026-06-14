@extends('layouts.app')

@section('title', trans('turkishpayment::messages.redirecting'))

@section('content')
<div class="container content">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            
            <h1 class="card-title h4 mb-3">
                {{ trans('turkishpayment::messages.redirecting') }}
            </h1>
            
            <p class="text-muted mb-4">
                {{ trans('turkishpayment::messages.redirecting_desc') }}
            </p>

            <form id="payment_form" action="{{ $url }}" method="{{ $method ?? 'POST' }}">
                @foreach($inputs as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                
                <button type="submit" class="btn btn-primary px-4">
                    {{ trans('turkishpayment::messages.click_if_not_redirected') }}
                </button>
            </form>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        document.getElementById('payment_form').submit();
                    }, 1000);
                });
            </script>
        </div>
    </div>
</div>
@endsection
