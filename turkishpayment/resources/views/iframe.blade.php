@extends('layouts.app')

@section('title', trans('paytrpayment::messages.payment_title'))

@section('content')
<div class="container content">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h1 class="card-title h4 mb-4">
                <i class="bi bi-credit-card-2-front-fill me-2 text-primary"></i>{{ trans('paytrpayment::messages.payment_title') }}
            </h1>
            
            <div style="min-height: 650px; position: relative;">
                <iframe src="https://www.paytr.com/odeme/guvenli/{{ $token }}" id="paytriframe" frameborder="0" scrolling="no" style="width: 100%; height: 100%; min-height: 650px; border: none;"></iframe>
            </div>
            
            <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
            <script>
                iFrameResize({}, '#paytriframe');
            </script>
        </div>
    </div>
</div>
@endsection
