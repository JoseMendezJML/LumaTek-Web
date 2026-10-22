@if($amount === null)
    {{ $unknown ?? 'Importe no registrado' }}
@else
    @php($parts = explode('.', (string) $amount, 2))
    ${{ number_format((int) $parts[0], 0, '.', ',') }}.{{ str_pad($parts[1] ?? '', 2, '0') }} {{ $currency }}
@endif
