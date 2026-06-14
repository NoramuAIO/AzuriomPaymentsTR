<div class="row g-3">
    <div class="mb-3 col-md-4">
        <label class="form-label" for="merchantIdInput">{{ trans('turkishpayment::messages.merchant_id') }} (Üye İşyeri No)</label>
        <input type="text" class="form-control @error('merchant-id') is-invalid @enderror" id="merchantIdInput" name="merchant-id" value="{{ old('merchant-id', $gateway->data['merchant-id'] ?? '') }}" required>
        @error('merchant-id')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-4">
        <label class="form-label" for="terminalIdInput">{{ trans('turkishpayment::messages.terminal_id') }} (Terminal ID)</label>
        <input type="text" class="form-control @error('terminal-id') is-invalid @enderror" id="terminalIdInput" name="terminal-id" value="{{ old('terminal-id', $gateway->data['terminal-id'] ?? '') }}" required>
        @error('terminal-id')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-4">
        <label class="form-label" for="storeKeyInput">{{ trans('turkishpayment::messages.store_key') }} (Provizyon Şifresi)</label>
        <input type="text" class="form-control @error('store-key') is-invalid @enderror" id="storeKeyInput" name="store-key" value="{{ old('store-key', $gateway->data['store-key'] ?? '') }}" required>
        @error('store-key')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>
</div>

<div class="alert alert-info mt-3">
    <p class="mb-0">
        <i class="bi bi-info-circle"></i>
        {!! trans('turkishpayment::messages.setup_instruction', [
            'callback_url' => '<code>' . route('shop.payments.notification', 'garanti') . '</code>'
        ]) !!}
    </p>
</div>
