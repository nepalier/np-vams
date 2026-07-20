<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
    h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
    h2 { font-size: 13px; border-bottom: 1px solid #185943; padding-bottom: 2px; margin-top: 18px; }
    .cover { text-align: center; margin-top: 80px; }
    .meta-table, .value-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .meta-table td, .value-table td, .value-table th { border: 1px solid #ccc; padding: 5px 8px; }
    .label { color: #555; width: 35%; }
    .declaration { margin-top: 24px; font-style: italic; }
    .signature-block { margin-top: 60px; }
    .footer-note { margin-top: 30px; font-size: 9px; color: #777; }
</style>
</head>
<body>

<div class="cover">
    <h1>Property Valuation Report</h1>
    <p>{{ $organization->name_en }}</p>
    <p>Assignment No: {{ $assignment->assignment_number }}</p>
    <p>Report No: {{ $report->report_number ?? '(draft — assigned at issuance)' }}</p>
    <p>{{ $assignment->assignment_date->format('Y-m-d') }}</p>
</div>

<div style="page-break-before: always;"></div>

<h2>1. Purpose of Valuation</h2>
<table class="meta-table">
    <tr><td class="label">Valuation Purpose</td><td>{{ $valuationPurpose }}</td></tr>
    <tr><td class="label">Client</td><td>{{ $client->name_en }}</td></tr>
    <tr><td class="label">Borrower</td><td>{{ $borrower->name_en ?? '—' }}</td></tr>
</table>

<h2>2. Property Location</h2>
@foreach ($properties as $property)
<table class="meta-table">
    <tr><td class="label">Property</td><td>{{ $property->property_name ?? $property->property_code }}</td></tr>
    <tr><td class="label">District</td><td>{{ $property->district->name_en ?? '—' }}</td></tr>
    <tr><td class="label">Local Level</td><td>{{ $property->localLevel->name_en ?? '—' }}</td></tr>
    <tr><td class="label">Address</td><td>{{ $property->address }}</td></tr>
</table>
@endforeach

<h2>3. Valuation Summary</h2>
<table class="value-table">
    <tr><th>Method</th><th>Value</th></tr>
    @foreach ($methodResults as $row)
    <tr><td>{{ $row['method'] }}</td><td>{{ number_format($row['value'], 2) }}</td></tr>
    @endforeach
</table>

<table class="value-table" style="margin-top:10px;">
    <tr><td class="label">Reconciled Market Value</td><td>{{ number_format($reconciliation->reconciled_market_value, 2) }}</td></tr>
    <tr><td class="label">Rounded Market Value</td><td>{{ number_format($reconciliation->rounded_market_value, 2) }}</td></tr>
    @if($reconciliation->government_minimum_value)
    <tr><td class="label">Government Minimum Value</td><td>{{ number_format($reconciliation->government_minimum_value, 2) }}</td></tr>
    @endif
    @if($reconciliation->distress_value)
    <tr><td class="label">Distress Value</td><td>{{ number_format($reconciliation->distress_value, 2) }}</td></tr>
    @endif
    @if($reconciliation->forced_sale_value)
    <tr><td class="label">Forced Sale Value</td><td>{{ number_format($reconciliation->forced_sale_value, 2) }}</td></tr>
    @endif
    @if($reconciliation->mortgage_value)
    <tr><td class="label">Mortgage Value</td><td>{{ number_format($reconciliation->mortgage_value, 2) }}</td></tr>
    @endif
</table>

<h2>4. Risk Assessment</h2>
<table class="meta-table">
    <tr><td class="label">Risk Category</td><td>{{ $riskCategory ?? 'Not assessed' }}</td></tr>
</table>

<h2>5. Assumptions and Limiting Conditions</h2>
<p>{{ $assumptionsText ?? 'This valuation is prepared solely for the stated purpose and client, subject to the assumptions and limiting conditions agreed at engagement.' }}</p>

<div class="declaration">
    I/We confirm that this valuation has been carried out impartially, without any conflict of interest, and reflects my/our professional opinion of value as at the date of inspection.
</div>

<div class="signature-block">
    <p>_____________________________</p>
    <p>{{ $signerName ?? '(Pending signature)' }}</p>
    <p>{{ $signerLicenseNumber ?? '' }}</p>
</div>

<div class="footer-note">
    This document is version {{ $versionNumber }} of report {{ $report->report_number ?? $assignment->assignment_number }}.
    Verify authenticity via the QR code / verification link on the signed copy.
</div>

</body>
</html>
