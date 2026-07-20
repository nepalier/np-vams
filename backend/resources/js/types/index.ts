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

export interface ApiEnvelope<T> {
  data: T;
  meta?: Record<string, unknown>;
}

export interface ApiError {
  errors: Array<{ status: string; title: string; detail: string }>;
}
