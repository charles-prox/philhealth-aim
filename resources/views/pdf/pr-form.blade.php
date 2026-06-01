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
    <title>Purchase Request - {{ $folder->pr_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 0.4in 0.5in 0.4in 0.5in;
        }

        * { box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        /* ══════════════════════════════════════
           LAYOUT TABLE SYSTEM (Repeating Header/Footer)
           ══════════════════════════════════════ */
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

        /* Header Style */
        .header-content {
            width: 100%;
            padding-bottom: 40px;
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

        /* Footer Style */
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

        /* Content block */
        #content {
            padding: 0;
        }

        /* ══════════════════════════════════════
           DOCUMENT STYLES
           ══════════════════════════════════════ */
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

        /* Signatory: keep entire table together, push to next page if it won't fit */
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
            text-decoration: underline;
            display: block;
            text-transform: uppercase;
        }
        .sig-pos {
            display: block;
            font-size: 10px;
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
                                {{-- PhilHealth logo — left --}}
                                <td class="ph-left">
                                    @if($philhealthLogo)
                                        <img src="data:image/png;base64,{{ $philhealthLogo }}"
                                             class="logo-philhealth" alt="PhilHealth Logo">
                                    @endif
                                </td>

                                {{-- Bagong Pilipinas — center --}}
                                <td class="ph-center">
                                    @if($bagongPilipinasLogo)
                                        <img src="data:image/png;base64,{{ $bagongPilipinasLogo }}"
                                             class="logo-pilipinas" alt="Bagong Pilipinas Logo">
                                    @endif
                                </td>

                                {{-- Vertical divider + address — always together, flush right --}}
                                <td class="ph-right">
                                    <span class="vline"></span>
                                    @if($philhealthAddress)
                                        <img src="data:image/png;base64,{{ $philhealthAddress }}"
                                             class="logo-address" alt="PhilHealth Regional Office">
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
                    <div id="content">

                        <div class="header-title">PURCHASE REQUEST</div>
                        <div class="entity-info">
                            Philippine Health Insurance Corporation - X
                        </div>
                        <table style="margin-bottom: 20px;width: 100%;">
                            <tr>
                                <td style="width: 10%;">
                                    <div style="margin-bottom: 5px;">Division:</div>
                                    
                                    
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
                                
                                <td style="width: 10%;"><div style="margin-bottom: 5px;">Section:</div></td>
                                <td style="border-bottom: 1px solid #000;"><div>General Services Unit (GSU)</div></td>
                                <td style="width: 30%;"> </td>
                                <td >
                                    <strong>Date:</strong>
                                </td>
                                <td style="border-bottom: 1px solid #000;">
                                     {{ $folder->created_at->format('F d, Y') }}
                                </td>
                            </tr>
                        </table>

                        {{-- PR Meta --}}
                        <table class="main-table">
                            

                            {{-- Column headers --}}
                            <tr class="text-center font-bold bg-gray">
                                <th style="width: 10%;">Item No.</th>
                                <th style="width: 10%;">Unit</th>
                                <th style="width: 45%;">Item Description</th>
                                <th style="width: 10%;">Quantity</th>
                                <th style="width: 10%;">Unit Cost</th>
                                <th style="width: 15%;">Total Cost</th>
                            </tr>

                            @php
                                $totalQty  = 0;
                                $totalUnitCost = 0;
                                $totalCost = 0;
                            @endphp

                            @forelse($folder->prItems as $index => $item)
                                @php
                                    $totalQty  += $item->total_qty;
                                    $totalUnitCost += $item->estimated_unit_cost;
                                    $itemTotal  = $item->estimated_total_cost;
                                    $totalCost += $itemTotal;
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td class="text-center">{{ $item->cobItem->unit ?? 'Unit' }}</td>
                                    <td>{{ $item->item_description_override ?? $item->cobItem->full_particulars ?? '' }}</td>
                                    <td class="text-center">{{ number_format($item->total_qty) }}</td>
                                    <td class="text-right">{{ number_format($item->estimated_unit_cost, 2) }}</td>
                                    <td class="text-right">{{ number_format($itemTotal, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 20px;">No items attached.</td>
                                </tr>
                            @endforelse

                            <!-- {{-- Padding rows — 30 minimum to force a 2nd page for header/footer testing --}}
                            @for($i = count($folder->prItems); $i < max(1, 25 - count($folder->prItems)); $i++)
                                <tr style="height: 22px;">
                                    <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td>
                                </tr>
                            @endfor -->

                            <tr class="font-bold">
                                <td colspan="3" class="text-right" style="padding-right: 15px;">TOTAL</td>
                                <td class="text-center">{{ number_format($totalQty) }}</td>
                                <td class="text-right">₱{{ number_format($totalUnitCost, 2) }}</td>
                                <td class="text-right" style="font-size: 13px;">₱{{ number_format($totalCost, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="6" style="padding: 10px; text-align: left;">
                                    We certify that the items and the corresponding amount listed above are based on the CY 2026 COB and within the approved 2026_APP. All Items requested under this PR SHALL NOT, Hereinafter, be available for realignment, unless within the prescribed period.
                                </td>
                            </tr>
                            {{-- Purpose Row --}}
                            <tr>
                                <td colspan="6" style="padding: 10px; text-align: left;">
                                    <span class="font-bold">Purpose:</span> {{ $folder->overall_purpose }}
                                </td>
                            </tr>
                        </table>

                        {{-- Signatories: wrapped so the whole block moves to the next page rather than splitting --}}
                        <div class="signatory-wrapper">
                            <table class="signatory-table">
                                <tr>
                                    <td style="width: 15%; text-align: left;"></td>
                                    <td style="width: 28%;" class="font-bold">Requested by:</td>
                                    <td style="width: 28%;" class="font-bold">Recommended by:</td>
                                    <td style="width: 28%;" class="font-bold">Approved by:</td>
                                </tr>
                                <tr>
                                    <td style="text-align: left; padding-bottom: 30px;">Signature:</td>
                                    <td></td><td></td><td></td>
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

                    </div>{{-- #content --}}
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
            <img src="data:image/png;base64,{{ $footerLogo }}"
                 class="logo-footer" alt="Accreditation Logo">
        @endif
    </div>

</body>
</html>
