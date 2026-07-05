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
    <title>Request for Quotation - {{ $folder->pr_number ?: $folder->tracking_number }}</title>
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

        .rfq-instruction {
            padding: 10px;
            border: 1px solid #000;
            margin-bottom: 15px;
            background-color: #f9f9fe;
            font-size: 11px;
            line-height: 1.4;
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

                        <div class="header-title">REQUEST FOR QUOTATION (RFQ)</div>
                        <div class="entity-info">
                            Philippine Health Insurance Corporation - X
                        </div>

                        <div class="rfq-instruction">
                            <strong>INSTRUCTIONS TO BIDDERS:</strong><br>
                            1. Please quote your lowest price inclusive of VAT on the item/s listed below, subject to the General Conditions.<br>
                            2. Submit your quotation duly signed by your representative not later than the deadline.<br>
                            3. All quotation sheets must be printed, filled out, and sealed.
                        </div>

                        <table style="margin-bottom: 20px; width: 100%; font-size: 11px;">
                            <tr>
                                <td style="width: 15%;"><strong>RFQ Control No.:</strong></td>
                                <td style="border-bottom: 1px solid #000; width: 35%;">{{ $folder->rfq_control_no ?: 'RFQ-' . ($folder->pr_number ?: $folder->tracking_number) }}</td>
                                <td style="width: 5%;"> </td>
                                <td style="width: 15%;"><strong>PR Reference:</strong></td>
                                <td style="border-bottom: 1px solid #000; width: 30%;">{{ $folder->pr_number ?: $folder->tracking_number }}</td>
                            </tr>
                            <tr>
                                <td>Date Issued:</td>
                                <td style="border-bottom: 1px solid #000;">{{ now()->format('F d, Y') }}</td>
                                <td> </td>
                                <td>Method:</td>
                                <td style="border-bottom: 1px solid #000;">Shopping / Small Value Procurement</td>
                            </tr>
                        </table>

                        <table class="main-table">
                            <tr class="text-center font-bold bg-gray">
                                <th style="width: 8%;">Item No.</th>
                                <th style="width: 10%;">Unit</th>
                                <th style="width: 42%;">Item Description & Technical Specifications</th>
                                <th style="width: 10%;">Quantity</th>
                                <th style="width: 15%;">Unit Price (Bid Offer)</th>
                                <th style="width: 15%;">Total Price (Bid Offer)</th>
                            </tr>

                            @forelse($folder->prItems as $index => $item)
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td class="text-center">{{ $item->unit ?? 'pcs' }}</td>
                                    <td>
                                        <strong>{{ $item->item_description_override }}</strong><br>
                                        <span style="font-size: 10px; color: #43474f;">[Vendor: Please state compliance/specifications in detail]</span>
                                    </td>
                                    <td class="text-center">{{ number_format($item->total_qty) }}</td>
                                    <td style="border: 1px solid #000; background-color: #fafafa;"></td>
                                    <td style="border: 1px solid #000; background-color: #fafafa;"></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 20px;">No items attached.</td>
                                </tr>
                            @endforelse

                            <tr style="height: 40px;">
                                <td></td>
                                <td></td>
                                <td class="text-center" style="vertical-align: top; padding-top: 10px; font-weight: bold;">***Nothing Follows***</td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </table>

                        <table style="width: 100%; margin-top: 30px; font-size: 11px;">
                            <tr>
                                <td style="width: 50%;">
                                    <p>Canvassed by:</p>
                                    <div style="height: 40px;"></div>
                                    <p style="border-top: 1px solid #000; width: 80%; text-align: center; font-weight: bold; text-transform: uppercase;">Procurement Custodian / Canvasser</p>
                                </td>
                                <td style="width: 50%;">
                                    <p>Bidder's Authorized Representative Certification:</p>
                                    <div style="height: 40px;"></div>
                                    <p style="border-top: 1px solid #000; width: 80%; text-align: center; font-weight: bold;">Signature Over Printed Name / Date</p>
                                </td>
                            </tr>
                        </table>

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
