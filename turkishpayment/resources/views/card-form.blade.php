@extends('layouts.app')

@section('title', trans('turkishpayment::messages.credit_card_payment'))

@section('content')
<div class="container content">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0">
                    <h3 class="mb-0 text-center">{{ $gatewayName }} 3D Secure Ödeme</h3>
                </div>
                
                <div class="card-body p-4">
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-shield-lock me-2"></i>
                        {{ trans('turkishpayment::messages.secure_payment_desc', ['amount' => $amount . ' ' . $currency]) }}
                    </div>

                    <form action="{{ $url }}" method="POST" id="payment_form">
                        @foreach($inputs as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <div class="mb-3">
                            <label class="form-label fw-bold" for="pan">{{ trans('turkishpayment::messages.card_number') }}</label>
                            <input type="text" class="form-control form-control-lg" id="pan" name="{{ $cardFields['pan'] ?? 'pan' }}" placeholder="0000 0000 0000 0000" maxlength="19" required>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label fw-bold">{{ trans('turkishpayment::messages.expiry_date') }}</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <select class="form-select form-select-lg" name="{{ $cardFields['month'] ?? 'Ecom_Payment_Card_ExpDate_Month' }}" required>
                                            <option value="" disabled selected>{{ trans('turkishpayment::messages.month') }}</option>
                                            @for($i = 1; $i <= 12; $i++)
                                                <option value="{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <select class="form-select form-select-lg" name="{{ $cardFields['year'] ?? 'Ecom_Payment_Card_ExpDate_Year' }}" required>
                                            <option value="" disabled selected>{{ trans('turkishpayment::messages.year') }}</option>
                                            @for($i = date('y'); $i <= date('y') + 15; $i++)
                                                <option value="{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}">{{ date('Y') > 2000 ? '20' . str_pad($i, 2, '0', STR_PAD_LEFT) : str_pad($i, 2, '0', STR_PAD_LEFT) }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold" for="cv2">CVV</label>
                                <input type="text" class="form-control form-control-lg" id="cv2" name="{{ $cardFields['cvv'] ?? 'cv2' }}" placeholder="123" maxlength="4" required>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                {{ trans('turkishpayment::messages.pay_amount', ['amount' => $amount . ' ' . $currency]) }}
                            </button>
                            <a href="{{ route('shop.cart.index') }}" class="btn btn-link text-muted">
                                {{ trans('turkishpayment::messages.cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer bg-transparent border-top-0 pb-4 text-center text-muted small">
                    <i class="bi bi-lock-fill"></i> İşleminiz 256-bit SSL sertifikası ile korunmaktadır.<br>
                    Kart bilgileriniz sistemimizde saklanmaz.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const panInput = document.getElementById('pan');
    
    panInput.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        let formattedValue = '';
        
        for (let i = 0; i < value.length; i++) {
            if (i > 0 && i % 4 === 0) {
                formattedValue += ' ';
            }
            formattedValue += value[i];
        }
        
        e.target.value = formattedValue;
    });

    document.getElementById('payment_form').addEventListener('submit', function(e) {
        // Kart numarasındaki boşlukları kaldırıp gönder (bankalar boşluksuz bekler)
        const panValue = panInput.value.replace(/\s+/g, '');
        
        // Gizli bir input oluşturup gerçek değeri ona aktar
        let hiddenPan = document.createElement('input');
        hiddenPan.type = 'hidden';
        hiddenPan.name = 'pan';
        hiddenPan.value = panValue;
        
        // Görünür inputun name özelliğini kaldır (böylece boşluklu hali gitmez)
        panInput.removeAttribute('name');
        
        this.appendChild(hiddenPan);
    });
});
</script>
@endsection
