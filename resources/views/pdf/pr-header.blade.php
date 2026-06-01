@php
    $philhealthLogo    = file_exists(public_path('images/philhealth-log.png'))
        ? base64_encode(file_get_contents(public_path('images/philhealth-log.png'))) : '';
    $bagongPilipinasLogo = file_exists(public_path('images/bagong-pilipinas-logo.png'))
        ? base64_encode(file_get_contents(public_path('images/bagong-pilipinas-logo.png'))) : '';
    $philhealthAddress = file_exists(public_path('images/philhealth-header-address.png'))
        ? base64_encode(file_get_contents(public_path('images/philhealth-header-address.png'))) : '';
@endphp
<style>
    .header-container {
        width: 100%;
        font-family: Arial, sans-serif;
        padding: 10px 13mm 10px 13mm;
    }
    .header-table {
        width: 100%;
        border-collapse: collapse;
    }
    .header-table td {
        border: none;
        padding: 0;
        vertical-align: middle;
    }
    .ph-left {
        width: 20%;
        text-align: left;
    }
    .ph-center {
        text-align: center;
    }
    .ph-right {
        width: 44%;
        text-align: right;
        white-space: nowrap;
    }
    .logo-philhealth {
        height: 60px;
        width: auto;
        vertical-align: middle;
    }
    .logo-pilipinas {
        height: 72px;
        width: auto;
        vertical-align: middle;
    }
    .logo-address {
        height: 62px;
        width: auto;
        vertical-align: middle;
    }
    .vline {
        display: inline-block;
        width: 1.5px;
        height: 66px;
        background-color: #000;
        vertical-align: middle;
        margin-right: 8px;
    }
</style>
<div class="header-container">
    <table class="header-table">
        <tr>
            <td class="ph-left">
                @if($philhealthLogo)
                    <img src="data:image/png;base64,{{ $philhealthLogo }}" class="logo-philhealth" alt="PhilHealth">
                @endif
            </td>
            <td class="ph-center">
                @if($bagongPilipinasLogo)
                    <img src="data:image/png;base64,{{ $bagongPilipinasLogo }}" class="logo-pilipinas" alt="Bagong Pilipinas">
                @endif
            </td>
            <td class="ph-right">
                <span class="vline"></span>
                @if($philhealthAddress)
                    <img src="data:image/png;base64,{{ $philhealthAddress }}" class="logo-address" alt="PhilHealth Address">
                @endif
            </td>
        </tr>
    </table>
</div>
