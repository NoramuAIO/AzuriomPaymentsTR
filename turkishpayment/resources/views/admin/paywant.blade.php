<div class="row g-3">
    <div class="mb-3 col-md-6">
        <label class="form-label" for="apiKeyInput">{{ trans('turkishpayment::messages.api_key') }}</label>
        <input type="text" class="form-control @error('api-key') is-invalid @enderror" id="apiKeyInput" name="api-key" value="{{ old('api-key', $gateway->data['api-key'] ?? '') }}" required>
        @error('api-key')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-6">
        <label class="form-label" for="apiSecretInput">{{ trans('turkishpayment::messages.api_secret') }}</label>
        <input type="text" class="form-control @error('api-secret') is-invalid @enderror" id="apiSecretInput" name="api-secret" value="{{ old('api-secret', $gateway->data['api-secret'] ?? '') }}" required>
        @error('api-secret')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>
</div>

<div class="alert alert-info">
    <p class="mb-0">
        <i class="bi bi-info-circle"></i>
        {!! trans('turkishpayment::messages.setup_instruction', [
            'callback_url' => '<code>' . route('shop.payments.notification', 'paywant') . '</code>'
        ]) !!}
    </p>
</div>
