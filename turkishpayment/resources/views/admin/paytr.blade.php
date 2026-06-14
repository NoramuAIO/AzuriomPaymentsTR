<div class="row g-3">
    <div class="mb-3 col-md-4">
        <label class="form-label" for="merchantIdInput">{{ trans('paytrpayment::messages.merchant_id') }}</label>
        <input type="text" class="form-control @error('merchant-id') is-invalid @enderror" id="merchantIdInput" name="merchant-id" value="{{ old('merchant-id', $gateway->data['merchant-id'] ?? '') }}" required>
        @error('merchant-id')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-4">
        <label class="form-label" for="merchantKeyInput">{{ trans('paytrpayment::messages.merchant_key') }}</label>
        <input type="text" class="form-control @error('merchant-key') is-invalid @enderror" id="merchantKeyInput" name="merchant-key" value="{{ old('merchant-key', $gateway->data['merchant-key'] ?? '') }}" required>
        @error('merchant-key')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-4">
        <label class="form-label" for="merchantSaltInput">{{ trans('paytrpayment::messages.merchant_salt') }}</label>
        <input type="text" class="form-control @error('merchant-salt') is-invalid @enderror" id="merchantSaltInput" name="merchant-salt" value="{{ old('merchant-salt', $gateway->data['merchant-salt'] ?? '') }}" required>
        @error('merchant-salt')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>
</div>

<div class="row g-3">
    <div class="mb-3 col-md-6">
        <div class="form-check">
            <input type="hidden" name="test-mode" value="0">
            <input type="checkbox" class="form-check-input @error('test-mode') is-invalid @enderror" id="testModeInput" name="test-mode" value="1" @checked(old('test-mode', $gateway->data['test-mode'] ?? false))>
            <label class="form-check-label" for="testModeInput">{{ trans('paytrpayment::messages.test_mode') }}</label>
            @error('test-mode')
            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
            @enderror
        </div>
    </div>

    <div class="mb-3 col-md-6">
        <label class="form-label" for="fallbackIpInput">{{ trans('paytrpayment::messages.fallback_ip') }}</label>
        <input type="text" class="form-control @error('fallback-ip') is-invalid @enderror" id="fallbackIpInput" name="fallback-ip" value="{{ old('fallback-ip', $gateway->data['fallback-ip'] ?? '') }}" placeholder="örn: 1.2.3.4">
        @error('fallback-ip')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
        <div class="form-text">{{ trans('paytrpayment::messages.fallback_ip_help') }}</div>
    </div>
</div>

<div class="alert alert-info">
    <p class="mb-0">
        <i class="bi bi-info-circle"></i>
        {!! trans('paytrpayment::messages.setup_instruction', [
            'callback_url' => '<code>' . route('shop.payments.notification', 'paytr') . '</code>'
        ]) !!}
    </p>
</div>
