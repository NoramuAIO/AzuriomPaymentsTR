<div class="row g-3">
    <div class="mb-3 col-md-6">
        <label class="form-label" for="apiKeyInput">{{ trans('turkishpayment::messages.api_key') }}</label>
        <input type="text" class="form-control @error('api-key') is-invalid @enderror" id="apiKeyInput" name="api-key" value="{{ old('api-key', $gateway->data['api-key'] ?? '') }}" required>
        @error('api-key')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-6">
        <div class="form-check mt-4 pt-2">
            <input type="hidden" name="test-mode" value="0">
            <input type="checkbox" class="form-check-input @error('test-mode') is-invalid @enderror" id="testModeInput" name="test-mode" value="1" @checked(old('test-mode', $gateway->data['test-mode'] ?? false))>
            <label class="form-check-label" for="testModeInput">{{ trans('turkishpayment::messages.test_mode') }}</label>
            @error('test-mode')
            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
            @enderror
        </div>
    </div>
</div>

<div class="alert alert-info">
    <p class="mb-0">
        <i class="bi bi-info-circle"></i>
        {!! trans('turkishpayment::messages.setup_instruction', [
            'callback_url' => '<code>' . route('shop.payments.notification', 'papara') . '</code>'
        ]) !!}
    </p>
</div>
