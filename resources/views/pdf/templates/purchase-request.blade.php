@php
    $philhealthLogo    = file_exists(public_path('images/philhealth-log.png'))
        ? base64_encode(file_get_contents(public_path('images/philhealth-log.png'))) : '';

    $bagongPilipinasLogo = file_exists(public_path('images/bagong-pilipinas-logo.png'))
        ? base64_encode(file_get_contents(public_path('images/bagong-pilipinas-logo.png'))) : '';

    $philhealthAddress = file_exists(public_path('images/philhealth-header-address.png'))
        ? base64_encode(file_get_contents(public_path('images/philhealth-header-address.png'))) : '';

    $footerLogo = file_exists(public_path('images/footer-logo.png'))
        ? base64_encode(file_get_contents(public_path('images/footer-logo.png'))) : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Request - {{ $folder->pr_number ?: $folder->tracking_number }}</title>
    <style>
        @font-face {
            font-family: 'Gelasio';
            src: url("{{ public_path('fonts/Gelasio/static/Gelasio-Regular.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Gelasio';
            src: url("{{ public_path('fonts/Gelasio/static/Gelasio-Bold.ttf') }}") format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Gelasio';
            src: url("{{ public_path('fonts/Gelasio/static/Gelasio-Italic.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: italic;
        }
        @font-face {
            font-family: 'Gelasio';
            src: url("{{ public_path('fonts/Gelasio/static/Gelasio-BoldItalic.ttf') }}") format('truetype');
            font-weight: bold;
            font-style: italic;
        }

        @page {
            size: A4;
            margin: 0.3in 0.3in 0.3in 0.3in;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Gelasio', Arial, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .layout-table {
            width: 100%;
            border-collapse: collapse;
            border: none !important;
        }

        .layout-table > thead > tr > td,
        .layout-table > tbody > tr > td,
        .layout-table > tfoot > tr > td {
            border: none !important;
            padding: 0 !important;
        }

        .header-content {
            width: 100%;
            padding-bottom: 20px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: none !important;
        }

        .header-table td {
            border: none !important;
            padding: 0 !important;
            vertical-align: middle;
        }

        .ph-left {
            width: 25%;
            text-align: left;
        }

        .ph-center {
            text-align: right;
            padding-right: 25px !important;
        }

        .ph-right {
            width: 45%;
            text-align: right;
            white-space: nowrap;
        }

        .vline {
            display: inline-block;
            width: 1.5px;
            height: 72px;
            background-color: #000;
            vertical-align: middle;
            margin-right: 8px;
        }

        .logo-philhealth { height: 66px; width: auto; display: inline-block; vertical-align: middle; }
        .logo-pilipinas  { height: 80px; width: auto; display: inline-block; vertical-align: middle; }
        .logo-address    { height: 70px; width: auto; display: inline-block; vertical-align: middle; }

        .footer-space {
            height: 70px;
        }

        .footer-fixed {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
        }

        .logo-footer { height: 56px; width: auto; }

        #content {
            padding: 0;
        }

        .header-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-top: 4px;
        }
        .entity-info {
            text-align: center;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .main-table th, .main-table td {
            border: 1px solid #000 !important;
            padding: 5px;
            vertical-align: top;
        }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .font-bold   { font-weight: bold; }
        .bg-gray     { background-color: #f3f4f6; }

        .signatory-wrapper {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-top: 10px;
        }

        .signatory-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signatory-table th,
        .signatory-table td {
            border: 1px solid #000 !important;
            text-align: center;
            padding: 10px 5px;
        }
        .sig-name {
            font-weight: bold;
            display: block;
            text-transform: uppercase;
        }
        .sig-pos {
            display: block;
            font-size: 11px;
        }
    </style>
</head>
<body>





    <table class="layout-table">
        <thead>
            <tr>
                <td>
                    <div class="header-content">
                        <table class="header-table">
                            <tr>
                                <td class="ph-left">
                                    @if($philhealthLogo)
                                        <img src="data:image/png;base64,{{ $philhealthLogo }}" class="logo-philhealth" alt="PhilHealth Logo">
                                    @endif
                                </td>

                                <td class="ph-center">
                                    @if($bagongPilipinasLogo)
                                        <img src="data:image/png;base64,{{ $bagongPilipinasLogo }}" class="logo-pilipinas" alt="Bagong Pilipinas Logo">
                                    @endif
                                </td>

                                <td class="ph-right">
                                    <span class="vline"></span>
                                    @if($philhealthAddress)
                                        <img src="data:image/png;base64,{{ $philhealthAddress }}" class="logo-address" alt="PhilHealth Regional Office">
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div id="content" style="position: relative;">

                        @if(($folder->status ?? '') === 'APPROVED')
                            <div style="
                                position: absolute;
                                top: 0;
                                right: 0;
                                font-size: 8px;
                                color: #4b5563;
                                font-family: monospace;
                                border: 1.5px solid #000;
                                padding: 4px 8px;
                                background-color: #f9fafb;
                                text-align: center;
                                line-height: 1.2;
                                z-index: 100;
                            ">
                                <strong>SYSTEM SECURITY CODE</strong><br>
                                <span style="font-size: 10px; font-weight: bold;">TRK-{{ strtoupper(substr(md5($folder->id), 0, 12)) }}</span>
                            </div>
                        @endif

                        <div class="header-title">PURCHASE REQUEST</div>
                        <div class="entity-info">
                            Philippine Health Insurance Corporation - X
                        </div>
                        <table style="margin-bottom: 20px;width: 100%;">
                            <tr>
                                <td style="width: 8%;">
                                    <div>Division:</div>
                                </td>
                                <td style="border-bottom: 1px solid #000;"><div>Management Services Division (MSD)</div></td>
                                <td style="width: 30%;"> </td>
                                <td >
                                    <strong>PR No.:</strong>
                                </td>
                                <td style="border-bottom: 1px solid #000;">
                                    <span style="font-size: 14px; font-weight: bold; ">{{ $folder->pr_number }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="width: 8%;"><div>Section:</div></td>
                                <td style="border-bottom: 1px solid #000;"><div>General Services Unit (GSU)</div></td>
                                <td style="width: 30%;"> </td>
                                <td >
                                    Date:
                                </td>
                                <td style="border-bottom: 1px solid #000;">
                                     {{ $folder->created_at ? $folder->created_at->format('F d, Y') : now()->format('F d, Y') }}
                                </td>
                            </tr>
                        </table>

                        <table class="main-table">
                            <tr class="text-center font-bold bg-gray">
                                <th style="width: 8%;">Item No.</th>
                                <th style="width: 10%;">Unit</th>
                                <th style="width: 45%;">Item Description</th>
                                <th style="width: 10%;">Quantity</th>
                                <th style="width: 12%;">Unit Cost</th>
                                <th style="width: 15%;">Total Cost</th>
                            </tr>

                            @php
                                $totalCost = 0;
                            @endphp

                            @forelse($folder->prItems as $index => $item)
                                @php
                                    $itemTotal = $item->estimated_total_cost;
                                    $totalCost += $itemTotal;
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td class="text-center">{{ $item->unit ?? 'pcs' }}</td>
                                    <td>{{ $item->item_description_override }}</td>
                                    <td class="text-center">{{ number_format($item->total_qty) }}</td>
                                    <td class="text-right">{{ number_format($item->estimated_unit_cost, 2) }}</td>
                                    <td class="text-right">{{ number_format($itemTotal, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 20px;">No items attached.</td>
                                </tr>
                            @endforelse

                             <tr style="height: 140px;">
                                 <td></td>
                                 <td></td>
                                 <td class="text-center" style="vertical-align: top; padding-top: 10px; font-weight: bold;">
                                     <div>***Nothing Follows***</div>
                                     @if($folder->budget_signed_at)
                                         <div style="margin-top: 15px; border: 1.5px solid #1e3a8a; padding: 6px 10px; background-color: #f0f4f8; display: inline-block; text-align: left; font-family: 'Courier New', monospace; font-size: 8.5px; color: #1e3a8a; font-weight: bold; border-radius: 4px; line-height: 1.3;">
                                             <div style="text-align: center; font-size: 9px; border-bottom: 1px solid #1e3a8a; padding-bottom: 2px; margin-bottom: 4px; letter-spacing: 0.5px;">BUDGET CERTIFIED</div>
                                             <div>PPA Code: {{ $folder->budget_ppa_code }}</div>
                                             <div>Budget Code: {{ $folder->budget_code }}</div>
                                             <div style="margin-top: 4px; border-top: 1px dashed #1e3a8a; padding-top: 2px; text-align: center; font-size: 7.5px;">
                                                 DIGITALLY SIGNED<br>
                                                 {{ $folder->budgetSignedBy->fullname ?? 'Budget Officer' }}<br>
                                                 {{ $folder->budget_signed_at->format('Y-m-d H:i:s') }}
                                             </div>
                                         </div>
                                     @endif
                                 </td>
                                 <td></td>
                                 <td></td>
                                 <td></td>
                             </tr>

                            <tr class="font-bold">
                                <td colspan="3" class="text-left">TOTAL</td>
                                <td></td>
                                <td></td>
                                <td class="text-right" style="font-size: 13px;">₱{{ number_format($totalCost, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="6" style="padding: 10px; text-align: left;">
                                    We certify that the items and the corresponding amount listed above are based on the CY 2026 COB and within the approved 2026_APP. All Items requested under this PR SHALL NOT, Hereinafter, be available for realignment, unless within the prescribed period.
                                </td>
                            </tr>
                            <tr>
                                <td colspan="1" style="padding: 10px; text-align: left; font-weight: bold; border-right: none !important;">
                                    Purpose:
                                </td>
                                <td colspan="5" style="padding: 10px; text-align: left; border-left: none !important;">
                                    {{ $folder->overall_purpose }}
                                </td>
                            </tr>
                        </table>

                        <div class="signatory-wrapper">
                            <table class="signatory-table">
                                <tr>
                                    <td style="width: 15%; text-align: left;"></td>
                                    <td style="width: 28%;" class="font-bold">Requested by:</td>
                                    <td style="width: 28%;" class="font-bold">Recommended by:</td>
                                    <td style="width: 28%;" class="font-bold">Approved by:</td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; padding-bottom: 30px; vertical-align: middle;">Signature:</td>
                                    <td style="vertical-align: middle; text-align: center; padding: 4px;">
                                        @if($folder->requested_signed_at)
                                            <div style="font-family: 'Courier New', monospace; font-size: 8px; color: #1e3a8a; line-height: 1.2; border: 1px dashed #1e3a8a; padding: 4px; display: inline-block;">
                                                <strong>DIGITALLY SIGNED</strong><br>
                                                {{ $folder->requested_signed_at->format('Y-m-d H:i:s') }}
                                            </div>
                                        @else
                                            <div style="height: 35px;"></div>
                                        @endif
                                    </td>
                                    <td style="vertical-align: middle; text-align: center; padding: 4px;">
                                        @if($folder->recommended_signed_at)
                                            <div style="font-family: 'Courier New', monospace; font-size: 8px; color: #1e3a8a; line-height: 1.2; border: 1px dashed #1e3a8a; padding: 4px; display: inline-block;">
                                                <strong>DIGITALLY SIGNED</strong><br>
                                                {{ $folder->recommended_signed_at->format('Y-m-d H:i:s') }}
                                            </div>
                                        @else
                                            <div style="height: 35px;"></div>
                                        @endif
                                    </td>
                                    <td style="vertical-align: middle; text-align: center; padding: 4px;">
                                        @if($folder->approved_signed_at)
                                            <div style="font-family: 'Courier New', monospace; font-size: 8px; color: #1e3a8a; line-height: 1.2; border: 1px dashed #1e3a8a; padding: 4px; display: inline-block;">
                                                <strong>DIGITALLY SIGNED</strong><br>
                                                {{ $folder->approved_signed_at->format('Y-m-d H:i:s') }}
                                            </div>
                                        @else
                                            <div style="height: 35px;"></div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: left;">Printed Name:</td>
                                    <td><span class="sig-name">{{ $folder->requestedBy->fullname ?? '' }}</span></td>
                                    <td><span class="sig-name">{{ $folder->recommendedBy->fullname ?? '' }}</span></td>
                                    <td><span class="sig-name">{{ $folder->approvedBy->fullname ?? '' }}</span></td>
                                </tr>
                                <tr>
                                    <td style="text-align: left;">Designation:</td>
                                    <td><span class="sig-pos">{{ $folder->requested_by_designation ?? 'Requesting Officer' }}</span></td>
                                    <td><span class="sig-pos">{{ $folder->recommended_by_designation ?? 'Recommending Officer' }}</span></td>
                                    <td><span class="sig-pos">{{ $folder->approved_by_designation ?? 'Approving Officer' }}</span></td>
                                </tr>
                            </table>
                        </div>

                    </div>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td>
                    <div class="footer-space"></div>
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="footer-fixed">
        @if($footerLogo)
            <img src="data:image/png;base64,{{ $footerLogo }}" class="logo-footer" alt="Accreditation Logo">
        @endif
    </div>

</body>
</html>
