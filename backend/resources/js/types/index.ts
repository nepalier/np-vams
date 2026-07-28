export interface AuthUser {
  id: string;
  name: string;
  name_ne?: string | null;
  email: string;
  organization_id: string | null;
  organization_branch_id: string | null;
  roles: string[];
  permissions: string[];
  mfa_enabled: boolean;
  last_login_at: string | null;
}

export interface Assignment {
  id: string;
  assignment_number: string;
  status: string;
  available_transitions: string[];
  client_id: string;
  client_name?: string | null;
  assignment_date: string | null;
  requested_completion_date: string | null;
  priority: 'low' | 'normal' | 'high' | 'urgent';
  valuation_purpose_id: number;
  valuation_purpose_name?: string | null;
  borrower_id: string | null;
  assigned_valuer_id: string | null;
  assigned_valuer_name?: string | null;
  assigned_approver_id: string | null;
  assigned_approver_name?: string | null;
  total_fee: string;
  payment_status: string;
  properties?: string[];
  created_at: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
  };
}

export interface ValuationCalculation {
  id: string;
  valuation_assignment_id: string;
  property_id: string | null;
  method: string;
  status: string;
  computed_value: string;
  computed_details: Record<string, unknown>;
  calculated_at: string;
}

export interface ReportVersion {
  id: string;
  version_number: number;
  format: string;
  file_hash_sha256: string;
  generated_at: string;
}

export interface Report {
  id: string;
  valuation_assignment_id: string;
  report_number: string | null;
  status: string;
  is_locked: boolean;
  current_version: ReportVersion | null;
}

export interface ApiEnvelope<T> {
  data: T;
  meta?: Record<string, unknown>;
}

export interface ApiError {
  errors: Array<{ status: string; title: string; detail: string }>;
}
