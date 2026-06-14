<div class="row g-3">
    <div class="mb-3 col-md-4">
        <label class="form-label" for="clientIdInput">{{ trans('turkishpayment::messages.client_id') }} (Mağaza No / Terminal ID)</label>
        <input type="text" class="form-control @error('client-id') is-invalid @enderror" id="clientIdInput" name="client-id" value="{{ old('client-id', $gateway->data['client-id'] ?? '') }}" required>
        @error('client-id')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-4">
        <label class="form-label" for="storeKeyInput">{{ trans('turkishpayment::messages.store_key') }} (3D Şifresi / Store Key)</label>
        <input type="text" class="form-control @error('store-key') is-invalid @enderror" id="storeKeyInput" name="store-key" value="{{ old('store-key', $gateway->data['store-key'] ?? '') }}" required>
        @error('store-key')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-4">
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
            'callback_url' => '<code>' . route('shop.payments.notification', 'akbank') . '</code>'
        ]) !!}
    </p>
</div>
