<div class="row g-3">
    <div class="mb-3 col-md-3">
        <label class="form-label" for="merchantIdInput">{{ trans('turkishpayment::messages.merchant_id') }} (Mağaza No)</label>
        <input type="text" class="form-control @error('merchant-id') is-invalid @enderror" id="merchantIdInput" name="merchant-id" value="{{ old('merchant-id', $gateway->data['merchant-id'] ?? '') }}" required>
        @error('merchant-id')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-3">
        <label class="form-label" for="customerIdInput">{{ trans('turkishpayment::messages.customer_id') }} (Müşteri No)</label>
        <input type="text" class="form-control @error('customer-id') is-invalid @enderror" id="customerIdInput" name="customer-id" value="{{ old('customer-id', $gateway->data['customer-id'] ?? '') }}" required>
        @error('customer-id')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-3">
        <label class="form-label" for="usernameInput">{{ trans('turkishpayment::messages.username') }}</label>
        <input type="text" class="form-control @error('username') is-invalid @enderror" id="usernameInput" name="username" value="{{ old('username', $gateway->data['username'] ?? '') }}" required>
        @error('username')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-3">
        <label class="form-label" for="passwordInput">{{ trans('turkishpayment::messages.password') }}</label>
        <input type="password" class="form-control @error('password') is-invalid @enderror" id="passwordInput" name="password" value="{{ old('password', $gateway->data['password'] ?? '') }}" required>
        @error('password')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-12">
        <div class="form-check pt-2">
            <input type="hidden" name="test-mode" value="0">
            <input type="checkbox" class="form-check-input @error('test-mode') is-invalid @enderror" id="testModeInput" name="test-mode" value="1" @checked(old('test-mode', $gateway->data['test-mode'] ?? false))>
            <label class="form-check-label" for="testModeInput">{{ trans('turkishpayment::messages.test_mode') }}</label>
        </div>
    </div>
</div>

<div class="alert alert-info mt-3">
    <p class="mb-0">
        <i class="bi bi-info-circle"></i>
        {!! trans('turkishpayment::messages.setup_instruction', [
            'callback_url' => '<code>' . route('shop.payments.notification', 'kuveytturk') . '</code>'
        ]) !!}
    </p>
</div>
