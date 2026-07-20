<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganizationType: string
{
    case ValuationFirm = 'valuation_firm';
    case CommercialBank = 'commercial_bank';
    case DevelopmentBank = 'development_bank';
    case FinanceCompany = 'finance_company';
    case MicrofinanceInstitution = 'microfinance_institution';
    case Cooperative = 'cooperative';
    case InsuranceCompany = 'insurance_company';
    case GovernmentAgency = 'government_agency';
    case Municipality = 'municipality';
    case RuralMunicipality = 'rural_municipality';
    case CorporateClient = 'corporate_client';
    case IndividualClient = 'individual_client';
    case OtherInstitution = 'other_institution';

    public function label(): string
    {
        return match ($this) {
            self::ValuationFirm => 'Valuation Firm',
            self::CommercialBank => 'Commercial Bank',
            self::DevelopmentBank => 'Development Bank',
            self::FinanceCompany => 'Finance Company',
            self::MicrofinanceInstitution => 'Microfinance Institution',
            self::Cooperative => 'Cooperative',
            self::InsuranceCompany => 'Insurance Company',
            self::GovernmentAgency => 'Government Agency',
            self::Municipality => 'Municipality',
            self::RuralMunicipality => 'Rural Municipality',
            self::CorporateClient => 'Corporate Client',
            self::IndividualClient => 'Individual Client',
            self::OtherInstitution => 'Other Institution',
        };
    }
}
