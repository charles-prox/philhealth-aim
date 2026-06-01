@php
    $footerLogo = file_exists(public_path('images/footer-logo.png'))
        ? base64_encode(file_get_contents(public_path('images/footer-logo.png'))) : '';
@endphp
<style>
    .footer-container {
        width: 100%;
        font-family: Arial, sans-serif;
        padding: 5px 13mm 5px 13mm;
    }
    .logo-footer {
        height: 52px;
        width: auto;
        vertical-align: middle;
    }
</style>
<div class="footer-container">
    @if($footerLogo)
        <img src="data:image/png;base64,{{ $footerLogo }}" class="logo-footer" alt="Accreditation Logo">
    @endif
</div>
