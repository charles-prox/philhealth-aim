@php
    $philhealthLogo    = file_exists(public_path('images/philhealth-log.png'))
        ? base64_encode(file_get_contents(public_path('images/philhealth-log.png'))) : '';

    $bagongPilipinasLogo = file_exists(public_path('images/bagong-pilipinas-logo.png'))
        ? base64_encode(file_get_contents(public_path('images/bagong-pilipinas-logo.png'))) : '';

    $philhealthAddress = file_exists(public_path('images/philhealth-header-address.png'))
        ? base64_encode(file_get_contents(public_path('images/philhealth-header-address.png'))) : '';

    $footerLogo = file_exists(public_path('images/footer-logo.png'))
        ? base64_encode(file_get_contents(public_path('images/footer-logo.png'))) : '';

    // Prepared by signatory logic based on office location (LHIO vs non-LHIO)
    $office = $folder->office;
    $isLhio = false;
    if ($office) {
        $acronym = $office->acronym;
        $section = $office->section;
        $sectionAcronym = $section ? $section->acronym : '';
        if (str_starts_with($acronym, 'LHIO-') || str_starts_with($sectionAcronym, 'LHIO-')) {
            $isLhio = true;
        }
    }

    if ($isLhio) {
        $preparedByName = $folder->requestedBy->fullname ?? 'Custodian';
        $preparedByDesig = $folder->requested_by_designation ?? 'Procurement Custodian';
        $preparedBySignedAt = $folder->requested_signed_at;
    } else {
        $acceptor = $folder->gsu_accepted_by_id ? \App\Models\Employee::find($folder->gsu_accepted_by_id) : null;
        if (!$acceptor) {
            $acceptor = \App\Models\Employee::whereHas('user', function($q) {
                $q->whereHas('roles', function($rq) {
                    $rq->where('name', 'Procurement Officer');
                });
            })->first();
        }
        
        $preparedByName = $acceptor ? $acceptor->fullname : 'Procurement Officer';
        $preparedByDesig = $acceptor ? $acceptor->designation : 'Procurement Officer';
        $preparedBySignedAt = $folder->gsu_accepted_at;
    }

    // Dynamic signatories from Signatory Registry
    $budgetOfficerId = \App\Models\SignatoryRegistry::getActiveSignatoryFor('BUDGET_OFFICER');
    $budgetOfficer = $budgetOfficerId ? \App\Models\Employee::find($budgetOfficerId) : null;

    $rvpSignerId = \App\Models\SignatoryRegistry::getActiveSignatoryFor('RVP');
    $rvpSigner = $rvpSignerId ? \App\Models\Employee::find($rvpSignerId) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approved Budget for the Contract - {{ $folder->pr_number ?: $folder->tracking_number }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0.3in 0.3in 0.3in 0.3in;
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
            padding-bottom: 15px;
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
            text-align: left;
        }

        .logo-footer { height: 56px; width: auto; }

        #content {
            padding: 0;
        }

        .header-title {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .entity-info {
            text-align: center;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 11px;
        }

        .main-table th, .main-table td {
            border: 1px solid #000 !important;
            padding: 5px;
            vertical-align: middle;
        }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .font-bold   { font-weight: bold; }
        .bg-gray     { background-color: #f3f4f6; }

        /* 3-Column Seamless Signatory Row */
        .signatures-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            font-size: 11px;
            align-items: stretch;
            margin-top: 25px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .sig-col {
            border: none;
            padding: 12px;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 140px;
        }

        .sig-col:last-child {
            margin-right: 0;
        }

        .sig-section {
            margin-bottom: 15px;
        }

        .sig-line {
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 5px;
        }

        .title-sub {
            font-size: 10px;
            color: #444;
            text-align: center;
            margin-top: 2px;
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

                        <div class="header-title">APPROVED BUDGET FOR THE CONTRACT (ABC)</div>
                        <div class="entity-info">
                            Philippine Health Insurance Corporation - X
                        </div>

                        <!-- Project Information Metadata Block -->
                        <table style="margin-bottom: 20px; width: 100%; font-size: 11px; border-collapse: collapse;">
                            <tr>
                                <td style="width: 12%; border: none; padding: 4px 0;"><strong>Project Name:</strong></td>
                                <td style="border: none; border-bottom: 1px solid #000; padding: 4px 5px; font-weight: bold;">{{ $folder->project_title ?: 'N/A' }}</td>
                            </tr>
                        </table>

                        <!-- Itemized Budget Allocation Table -->
                        <table class="main-table">
                            <thead>
                                <tr class="text-center font-bold bg-gray">
                                    <th style="width: 6%;">Item No.<br>(A)</th>
                                    <th style="width: 32%;">Description<br>(b)</th>
                                    <th style="width: 7%;">Quantity<br>(c)</th>
                                    <th style="width: 6%;">Unit<br>(d)</th>
                                    <th style="width: 11%;">Current Market<br>Price Per Unit<br>(e)</th>
                                    <th style="width: 9%;">No. of Days/Nights<br>(If Applicable)<br>(f)</th>
                                    <th style="width: 10%;">Sub-Total<br>(g)</th>
                                    <th style="width: 9%;">5% Contingency for<br>Price Escalation (h)<br>=(g)(5%)</th>
                                    <th style="width: 10%;">Total Cost (i)<br>=(g)+(h)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $sumSubTotal = 0;
                                    $sumTotalCost = 0;
                                @endphp

                                @forelse($folder->prItems as $index => $item)
                                    @php
                                        $subTotal = (float) $item->estimated_total_cost;
                                        // Contingency column is left empty as instructed, so Total Cost is just Sub-Total
                                        $totalCost = $subTotal;

                                        $sumSubTotal += $subTotal;
                                        $sumTotalCost += $totalCost;
                                    @endphp
                                    <tr>
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td><strong>{{ $item->item_description_override }}</strong></td>
                                        <td class="text-center">{{ number_format($item->total_qty) }}</td>
                                        <td class="text-center">{{ $item->unit ?? 'pcs' }}</td>
                                        <td class="text-right">₱{{ number_format($item->estimated_unit_cost, 2) }}</td>
                                        <td class="text-center"></td> {{-- Empty: Days/Nights --}}
                                        <td class="text-right">₱{{ number_format($subTotal, 2) }}</td>
                                        <td class="text-center"></td> {{-- Empty: Contingency --}}
                                        <td class="text-right font-bold">₱{{ number_format($totalCost, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center" style="padding: 20px;">No items attached.</td>
                                    </tr>
                                @endforelse

                                <tr style="height: 40px;">
                                    <td></td>
                                    <td class="text-center" style="vertical-align: top; padding-top: 10px; font-weight: bold;">***Nothing Follows***</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>

                                <tr class="font-bold bg-gray">
                                    <td colspan="6" class="text-right uppercase">TOTAL:</td>
                                    <td class="text-right">₱{{ number_format($sumSubTotal, 2) }}</td>
                                    <td class="text-center"></td>
                                    <td class="text-right text-emerald-800" style="font-size: 12px;">₱{{ number_format($sumTotalCost, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Standardized Clean 3-Column Signatory Layout -->
                        <div class="signatures-row">

                            <!-- Column 1: Preparation Component -->
                            <div class="sig-col">
                                <div class="sig-section">
                                    <p><strong>Prepared by:</strong></p>
                                    @if($preparedBySignedAt)
                                        <div style="font-family: 'Courier New', monospace; font-size: 8px; color: #1e3a8a; line-height: 1.2; border: 1px dashed #1e3a8a; padding: 4px; display: inline-block; margin-top: 10px; text-align: center; width: 100%;">
                                            <strong>DIGITALLY SIGNED</strong><br>
                                            {{ $preparedBySignedAt->format('Y-m-d H:i:s') }}
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <div class="sig-line">
                                        <strong>{{ $preparedByName }}</strong>
                                        <div class="title-sub">{{ $preparedByDesig }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Column 2: Fiscal Certification Component -->
                            <div class="sig-col">
                                <div class="sig-section">
                                    <p><strong>Certified funded in COB:</strong></p>
                                </div>
                                <div>
                                    <div class="sig-line">
                                        <strong>{{ $budgetOfficer ? $budgetOfficer->fullname : 'ALIAH B. ASUM' }}</strong>
                                        <div class="title-sub">{{ $budgetOfficer ? $budgetOfficer->designation : 'Budget Officer Designate' }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Column 3: Executive Approval Component -->
                            <div class="sig-col">
                                <div class="sig-section">
                                    <p><strong>Approved By:</strong></p>
                                    @if($folder->approved_signed_at)
                                        <div style="font-family: 'Courier New', monospace; font-size: 8px; color: #1e3a8a; line-height: 1.2; border: 1px dashed #1e3a8a; padding: 4px; display: inline-block; margin-top: 10px; text-align: center; width: 100%;">
                                            <strong>DIGITALLY SIGNED</strong><br>
                                            {{ $folder->approved_signed_at->format('Y-m-d H:i:s') }}
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <div class="sig-line">
                                        <strong>{{ $rvpSigner ? $rvpSigner->fullname : 'DELIO A. ASERON II' }}</strong>
                                        <div class="title-sub">{{ $rvpSigner ? $rvpSigner->designation : 'Regional Vice President, PRO X' }}</div>
                                    </div>
                                </div>
                            </div>

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
