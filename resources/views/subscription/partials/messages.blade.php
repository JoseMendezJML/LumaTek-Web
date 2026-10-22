@if(session('status'))
    <p class="subscription-notice" role="status">{{ session('status') }}</p>
@endif
@if($errors->any())
    <div class="subscription-errors" role="alert">
        <p>No fue posible completar la solicitud:</p>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
