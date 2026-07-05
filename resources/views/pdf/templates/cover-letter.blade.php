@php
    $philhealthLogo    = file_exists(public_path('images/philhealth-log.png'))
        ? base64_encode(file_get_contents(public_path('images/philhealth-log.png'))) : '';

    $bagongPilipinasLogo = file_exists(public_path('images/bagong-pilipinas-logo.png'))
        ? base64_encode(file_get_contents(public_path('images/bagong-pilipinas-logo.png'))) : '';

    $philhealthAddress = file_exists(public_path('images/philhealth-header-address.png'))
        ? base64_encode(file_get_contents(public_path('images/philhealth-header-address.png'))) : '';

    $footerLogo = file_exists(public_path('images/footer-logo.png'))
        ? base64_encode(file_get_contents(public_path('images/footer-logo.png'))) : '';

    // Dynamic GSU Head lookup from matrix
    $gsuOffice = \App\Models\Office::where('acronym', 'GSU')->first();
    $gsuHeadId = $gsuOffice ? \App\Models\SignatoryRegistry::getActiveSignatoryFor('UNIT_HEAD', $gsuOffice->id) : null;
    $gsuHead = $gsuHeadId ? \App\Models\Employee::find($gsuHeadId) : null;

    $recipientName = $gsuHead ? $gsuHead->fullname : 'GLADYS A. ELTANAL';
    $recipientDesig = $gsuHead ? $gsuHead->designation : 'Head, General Services Unit';

    // From Office Name
    $fromOffice = $folder->office ? $folder->office->name : ($folder->requesting_unit ?? 'End-User Office');

    // Requester (Custodian)
    $requesterName = $folder->requestedBy->fullname ?? 'Custodian';
    $requesterDesig = $folder->requested_by_designation ?? 'Document Custodian';
    $preparedBySignedAt = $folder->requested_signed_at;

    // Dynamically build document list
    $documentList = [];
    $documentList[] = "Three (3) copies of Purchase Request";
    
    // Check if ABC is required (unit cost is greater than zero)
    $hasABC = $folder->prItems->sum(fn($item) => (float) ($item->estimated_unit_cost ?? $item->unit_cost ?? 0)) > 0;
    if ($hasABC) {
        $documentList[] = "ABC";
    }

    // Get user-uploaded attachments
    $userAttachments = $folder->attachments()
        ->where('attachment_type', 'USER_OTHER')
        ->get();

    foreach ($userAttachments as $attach) {
        // Strip the file extension for printed listing visual style
        $displayName = pathinfo($attach->original_name, PATHINFO_FILENAME);
        $documentList[] = $displayName;
    }

    $documentList[] = "Annual Procurement Plan (APP)";
    $documentList[] = "Checklist";
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Request Endorsement Memorandum - {{ $folder->pr_number ?: $folder->tracking_number }}</title>
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
            text-align: left;
        }

        .logo-footer { height: 56px; width: auto; }

        #content {
            padding: 0;
        }

        /* Memo Header Information Meta Table */
        .memo-meta-table {
            width: 100%;
            margin-bottom: 20px;
            font-size: 12px;
            border-collapse: collapse;
        }

        .memo-meta-table td {
            padding: 6px 0;
            vertical-align: middle;
            border: none !important;
        }

        .memo-meta-table td.label {
            width: 120px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .memo-meta-table td.separator {
            width: 20px;
            font-weight: bold;
            text-align: center;
        }

        .memo-meta-table td.value {
            border-bottom: none !important;
            font-weight: bold;
            padding: 4px 5px;
        }

        .divider-line {
            border-top: 1px solid #000;
            margin-bottom: 25px;
        }

        /* Body Content formatting */
        .memo-body {
            font-size: 13px;
            line-height: 1.6;
            text-align: justify;
        }

        .memo-body p {
            margin-bottom: 15px;
        }

        .document-list {
            margin: 15px 0 25px 20px;
            padding: 0;
            list-style-type: none;
        }

        .document-list li {
            margin-bottom: 8px;
            position: relative;
        }

        /* Signatory Layout */
        .signature-block {
            margin-top: 40px;
            width: 250px;
            font-size: 13px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .signature-title {
            margin-bottom: 40px;
        }

        .sig-container {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .sig-name {
            font-weight: bold;
            display: block;
            text-transform: uppercase;
            border-top: 1px solid #000;
            padding-top: 5px;
            text-align: center;
        }

        .sig-desig {
            display: block;
            font-size: 11px;
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

                        <!-- Memorandum Routing Block -->
                        <table class="memo-meta-table">
                            <tr>
                                <td class="label">Date</td>
                                <td class="separator">:</td>
                                <td class="value">{{ now()->format('F d, Y') }}</td>
                            </tr>
                            <tr>
                                <td class="label">FOR/TO</td>
                                <td class="separator">:</td>
                                <td class="value">
                                    <span style="text-transform: uppercase;">{{ $recipientName }}</span><br>
                                    <span style="font-size: 11px; color: #444; font-weight: normal;">{{ $recipientDesig }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="label">FROM</td>
                                <td class="separator">:</td>
                                <td class="value">{{ $fromOffice }}</td>
                            </tr>
                            <tr>
                                <td class="label">SUBJECT</td>
                                <td class="separator">:</td>
                                <td class="value" style="text-transform: uppercase;">Purchase Request Endorsement</td>
                            </tr>
                        </table>

                        <div class="divider-line"></div>

                        <!-- Memorandum Context Body -->
                        <div class="memo-body">
                            <p>Good day.</p>
                            <p>
                                Respectfully endorsing herewith the attachments to request for Approved Purchase Request 
                                <strong>"{{ $folder->overall_purpose }}"</strong>.
                            </p>
                            <p>Attached are the following documents for your review and appropriate action:</p>
                            
                            <ul class="document-list">
                                @foreach($documentList as $index => $docName)
                                    <li>{{ $index + 1 }}. {{ $docName }}</li>
                                @endforeach
                            </ul>

                            <p>We hope for your usual support and immediate processing of the above-mentioned request.</p>
                            <p>Thank you.</p>
                        </div>

                        <!-- Intact Closing & Signatory Block -->
                        <div class="signature-block">
                            <div class="signature-title">Very truly yours,</div>
                            
                            @if($preparedBySignedAt)
                                <div style="font-family: 'Courier New', monospace; font-size: 8px; color: #1e3a8a; line-height: 1.2; border: 1px dashed #1e3a8a; padding: 4px; display: inline-block; margin-bottom: 8px; text-align: center; width: 100%;">
                                    <strong>DIGITALLY SIGNED</strong><br>
                                    {{ $preparedBySignedAt->format('Y-m-d H:i:s') }}
                                </div>
                            @else
                                <div style="height: 35px;"></div>
                            @endif

                            <div class="sig-container">
                                <span class="sig-name">{{ $requesterName }}</span>
                                <span class="sig-desig">{{ $requesterDesig }}</span>
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
