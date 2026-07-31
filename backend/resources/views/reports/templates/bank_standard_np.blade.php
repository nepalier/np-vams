<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.4; }
    h1 { font-size: 17px; text-align: center; margin-bottom: 4px; }
    h2 { font-size: 13px; border-bottom: 1px solid #185943; padding-bottom: 2px; margin-top: 20px; }
    h3 { font-size: 11.5px; margin-top: 14px; margin-bottom: 4px; }
    .cover { text-align: center; margin-top: 60px; }
    .submit-blocks { display: table; width: 100%; margin-top: 40px; }
    .submit-block { display: table-cell; width: 50%; vertical-align: top; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.data td, table.data th { border: 1px solid #999; padding: 5px 8px; }
    table.data th { background: #f0f0f0; text-align: left; }
    .label { color: #555; width: 35%; }
    .declaration-list { margin-top: 10px; padding-left: 18px; }
    .declaration-list li { margin-bottom: 6px; }
    .amount-highlight { font-weight: bold; }
    .in-words { font-style: italic; color: #333; }
    .signature-row { display: table; width: 100%; margin-top: 60px; }
    .signature-col { display: table-cell; width: 33.33%; text-align: center; padding-top: 40px; border-top: 1px solid #333; }
    .footer-note { margin-top: 30px; font-size: 9px; color: #777; }
    .page-break { page-break-before: always; }
    .limitation-box { border: 1px solid #ccc; padding: 10px; margin-top: 10px; font-size: 10px; }
</style>
</head>
<body>

{{-- ===================== COVER PAGE ===================== --}}
<div class="cover">
    <h1>VALUATION REPORT</h1>
    <p style="margin-top: 20px;"><strong>Client:</strong> {{ $borrower->name_en ?? $client->name_en }}</p>
    <p><strong>Owner of the Property:</strong> {{ $borrower->name_en ?? '—' }}</p>
    @foreach ($properties as $property)
        <p><strong>Location of the Property:</strong> {{ $property->address ?? '—' }}, {{ $property->district?->name_en ?? '' }}</p>
    @endforeach
    <p><strong>Contact No.:</strong> {{ $borrower->mobile ?? '—' }}</p>
</div>

<div class="submit-blocks">
    <div class="submit-block">
        <strong>SUBMITTED TO:</strong><br>
        {{ $client->name_en }}<br>
        {{ $client->address ?? '' }}
    </div>
    <div class="submit-block">
        <strong>SUBMITTED BY:</strong><br>
        {{ $organization?->name_en }}<br>
        {{ $organization?->address ?? '' }}
    </div>
</div>

<p style="margin-top: 60px;">Date: {{ optional($assignment->assignment_date)->format('jS M., Y') }}</p>

<div class="page-break"></div>

{{-- ===================== SUBMISSION LETTER + DECLARATION ===================== --}}
<p>To,<br>The Manager,<br>{{ $client->name_en }}</p>

<p><strong>Subject: Assessment of Property Values</strong></p>

<p>Dear Sir/Madam,</p>

<p>
    We are pleased to submit herewith the Valuation Report of the property described below, based on present
    market value, intended to be mortgaged in favour of {{ $client->name_en }} by
    {{ $borrower->name_en ?? 'the client' }}.
</p>

<h3>It is our considered opinion that the Fair Market Value and Distress Value of the above-mentioned property are as follows:</h3>

<table class="data">
    <tr>
        <th style="width: 60%">Details</th>
        <th>Amount (NRs.)</th>
    </tr>
    <tr>
        <td>{{ $certificateSummary['weighted_fair_market_value_label'] }}</td>
        <td class="amount-highlight">{{ number_format($certificateSummary['weighted_fair_market_value'], 2) }}</td>
    </tr>
    <tr>
        <td colspan="2" class="in-words">In Words: {{ $certificateSummary['weighted_fair_market_value_in_words'] }}</td>
    </tr>
    <tr>
        <td>{{ $certificateSummary['distress_value_label'] }}</td>
        <td class="amount-highlight">{{ number_format($certificateSummary['distress_value'], 2) }}</td>
    </tr>
    <tr>
        <td colspan="2" class="in-words">In Words: {{ $certificateSummary['distress_value_in_words'] }}</td>
    </tr>
</table>

<p>
    All legal documents submitted by the client, the necessary calculations, and recently taken photographs of
    the proposed site are enclosed for your record and perusal. We hereby declare and certify that:
</p>

<ol class="declaration-list">
    @foreach ($certificateSummary['declarations'] as $declaration)
        <li>{{ $declaration }}</li>
    @endforeach
</ol>

@if ($certificateSummary['comments'])
    <p><strong>Comments:</strong> {{ $certificateSummary['comments'] }}</p>
@endif

<div class="signature-row">
    <div class="signature-col">
        <div>Site Visited by</div>
    </div>
    <div class="signature-col">
        <div>Prepared by</div>
    </div>
    <div class="signature-col">
        <div>Checked by</div>
        @if ($signerName)
            <div style="margin-top: 4px; font-size: 9px;">{{ $signerName }}@if($signerLicenseNumber) (NEC Reg. {{ $signerLicenseNumber }})@endif</div>
        @endif
    </div>
</div>

<div class="page-break"></div>

{{-- ===================== GENERAL INFORMATION ===================== --}}
<h2>General Information</h2>

<h3>Client's Information</h3>
<table class="data">
    <tr><td class="label">Name</td><td>{{ $borrower->name_en ?? $client->name_en }}</td></tr>
    <tr><td class="label">Citizenship No.</td><td>{{ $borrower->citizenship_number ?? '—' }}</td></tr>
    <tr><td class="label">Address</td><td>{{ $borrower->permanent_address ?? '—' }}</td></tr>
    <tr><td class="label">Contact No.</td><td>{{ $borrower->mobile ?? '—' }}</td></tr>
</table>

<h3>Purpose of Valuation</h3>
<table class="data">
    <tr><td class="label">Purpose</td><td>{{ $valuationPurpose }}</td></tr>
    <tr><td class="label">Date of Valuation</td><td>{{ optional($assignment->assignment_date)->format('jS M., Y') }}</td></tr>
</table>

@foreach ($properties as $property)
<h3>Property: {{ $property->property_name ?? $property->property_code }}</h3>
<table class="data">
    <tr><td class="label">Address</td><td>{{ $property->address ?? '—' }}</td></tr>
    <tr><td class="label">District</td><td>{{ $property->district?->name_en ?? '—' }}</td></tr>
    <tr><td class="label">Local Level</td><td>{{ $property->localLevel?->name_en ?? '—' }}</td></tr>
    @if ($property->latitude && $property->longitude)
        <tr><td class="label">GPS Coordinates</td><td>{{ $property->latitude }}, {{ $property->longitude }}</td></tr>
    @endif
</table>
@endforeach

{{-- ===================== VALUE OF LAND ===================== --}}
@if ($landRateCalculation)
<div class="page-break"></div>
<h2>Value of Land</h2>
<p>
    Weighted Rate = (Government Rate &times; {{ $landRateCalculation->computed_details['government_weight_pct'] }}%)
    + (Market Rate &times; {{ $landRateCalculation->computed_details['market_weight_pct'] }}%)
</p>
<table class="data">
    <tr>
        <th>Plot</th>
        <th>Area Considered</th>
        <th>Government Rate</th>
        <th>Market Rate</th>
        <th>Weighted Rate</th>
        <th>Value of Land</th>
    </tr>
    @foreach ($landRateCalculation->computed_details['plots'] as $plot)
    <tr>
        <td>{{ $plot['plot_label'] }}</td>
        <td>{{ $plot['area_considered'] }}</td>
        <td>{{ number_format($plot['government_rate'], 2) }}</td>
        <td>{{ number_format($plot['market_rate'], 2) }}</td>
        <td>{{ number_format($plot['weighted_rate'], 2) }}</td>
        <td>{{ number_format($plot['plot_value'], 2) }}</td>
    </tr>
    @endforeach
    <tr>
        <td colspan="5" style="text-align: right;"><strong>Total Value of Land</strong></td>
        <td class="amount-highlight">{{ number_format($landRateCalculation->computed_details['total_land_value'], 2) }}</td>
    </tr>
</table>
@endif

{{-- ===================== VALUE OF BUILDING ===================== --}}
@if ($buildingCostCalculation)
<div class="page-break"></div>
<h2>Value of Building</h2>
<table class="data">
    <tr>
        <th>Floor</th>
        <th>Area</th>
        <th>Rate</th>
        <th>Civil Works Cost</th>
    </tr>
    @foreach ($buildingCostCalculation->computed_details['floors'] as $floor)
    <tr>
        <td>{{ $floor['floor_name'] }}</td>
        <td>{{ $floor['area'] }}</td>
        <td>{{ number_format($floor['rate_per_unit_area'], 2) }}</td>
        <td>{{ number_format($floor['civil_works_cost'], 2) }}</td>
    </tr>
    @endforeach
    <tr>
        <td colspan="3" style="text-align: right;">Total Civil Works Cost</td>
        <td>{{ number_format($buildingCostCalculation->computed_details['total_civil_works_cost'], 2) }}</td>
    </tr>
    <tr>
        <td colspan="3" style="text-align: right;">Sanitary Fixture ({{ $buildingCostCalculation->computed_details['sanitary_fixture_pct'] }}%)</td>
        <td>{{ number_format($buildingCostCalculation->computed_details['sanitary_cost'], 2) }}</td>
    </tr>
    <tr>
        <td colspan="3" style="text-align: right;">Electrical Fixture ({{ $buildingCostCalculation->computed_details['electrical_fixture_pct'] }}%)</td>
        <td>{{ number_format($buildingCostCalculation->computed_details['electrical_cost'], 2) }}</td>
    </tr>
    <tr>
        <td colspan="3" style="text-align: right;"><strong>Cost of Building &amp; Fixture</strong></td>
        <td>{{ number_format($buildingCostCalculation->computed_details['cost_of_building_and_fixture'], 2) }}</td>
    </tr>
    <tr>
        <td colspan="3" style="text-align: right;">
            Depreciation ({{ $buildingCostCalculation->computed_details['depreciation_pct_per_annum'] }}%/annum
            &times; {{ $buildingCostCalculation->computed_details['age_years'] }} years
            = {{ $buildingCostCalculation->computed_details['total_depreciation_pct'] }}%)
        </td>
        <td>{{ number_format($buildingCostCalculation->computed_details['depreciation_amount'], 2) }}</td>
    </tr>
    <tr>
        <td colspan="3" style="text-align: right;"><strong>Actual Construction Cost of Building &amp; Fixture</strong></td>
        <td class="amount-highlight">{{ number_format($buildingCostCalculation->computed_details['actual_construction_cost'], 2) }}</td>
    </tr>
</table>
@endif

{{-- ===================== SUMMARY OF PROPERTY VALUE ===================== --}}
<div class="page-break"></div>
<h2>Summary of Property Value</h2>
<table class="data">
    <tr>
        <th>Component</th>
        <th>Value (NRs.)</th>
    </tr>
    @if ($landRateCalculation)
        <tr><td>Land</td><td>{{ number_format($landRateCalculation->computed_details['total_land_value'], 2) }}</td></tr>
    @endif
    @if ($buildingCostCalculation)
        <tr><td>Building</td><td>{{ number_format($buildingCostCalculation->computed_details['actual_construction_cost'], 2) }}</td></tr>
    @endif
    <tr>
        <td><strong>Reconciled Market Value</strong></td>
        <td class="amount-highlight">{{ number_format($reconciliation->reconciled_market_value, 2) }}</td>
    </tr>
    <tr>
        <td><strong>Distress Value</strong></td>
        <td>{{ number_format($certificateSummary['distress_value'], 2) }}</td>
    </tr>
</table>

{{-- ===================== TERMINOLOGY ===================== --}}
<h2>Terminology</h2>
<p><strong>Fair Market Value:</strong> {{ $certificateSummary['weighted_fair_market_value_in_words'] }}</p>
<p><strong>Distress Value:</strong> {{ $certificateSummary['distress_value_in_words'] }}</p>

{{-- ===================== REMARKS AND LIMITING CONDITIONS ===================== --}}
<h2>Remarks and Limiting Conditions</h2>
<div class="limitation-box">
    <p><strong>Cost price does not necessarily mean the value of an asset.</strong> Circumstances and immediate
    surroundings of that asset along with market trends must be analyzed to arrive at the true and most probable
    market value of an asset.</p>

    <p><strong>This valuation was conducted for the sole purpose of establishing the Fair Market Value of the
    property for the client and the lending institution</strong> for use as mortgage purpose, without any intent
    of bias or prejudice. No part of this report may be reproduced, made public, or circulated without prior
    written consent.</p>

    <p><strong>Value is effective as of the date of inspection.</strong> The opinion of the valuer is based on
    the prevailing market rate of construction, comparative instances of sale, market trends and future
    probabilities. The valuer does not take responsibility for the volatility of the market or the ability to
    purchase the property at the valued price.</p>
</div>

<h2>Opinion</h2>
<p>
    In our opinion, this property may be taken as mortgage for the Fair Market Value amount recommended in this
    valuation certificate. However, all remarks made above shall be taken into consideration and all legal
    documents shall be scrutinized by a legal expert.
</p>

<h2>Declaration</h2>
<p>
    We certify that our firm is fully authorized to carry out this valuation work under prevailing law, and that
    we are fully equipped and competent to carry out this assignment with the necessary qualifications, skills,
    and experience required. We also certify that no individual in our firm has any financial interest in the
    said property. To the best of our knowledge, all matters of a factual nature discussed in this report are
    true and correct; no important factors have been intentionally overlooked or withheld.
</p>

<div class="signature-row">
    <div class="signature-col">
        Site Visited by
    </div>
    <div class="signature-col">
        Prepared by
    </div>
    <div class="signature-col">
        Checked by
        @if ($signerName)
            <div style="margin-top: 4px; font-size: 9px;">{{ $signerName }}@if($signerLicenseNumber) (NEC Reg. {{ $signerLicenseNumber }})@endif</div>
        @endif
    </div>
</div>

<div class="footer-note">
    Report No: {{ $report->report_number ?? '(draft — assigned at issuance)' }} | Version {{ $versionNumber }}
    | Generated by {{ $organization?->name_en }} via NP-VAMS
</div>

</body>
</html>
