/**
 * Safe Query Builder
 * 
 * This module defines the allowed queries that the AI MVP server can make
 * to the main Swift Pay application. It acts as a whitelist to ensure
 * the AI can only access predefined, safe endpoints.
 */

export interface AllowedQuery {
  name: string;
  description: string;
  endpoint: string;
  method: 'GET';
}

/**
 * Whitelist of allowed queries.
 * The AI MVP server can ONLY make requests to these endpoints.
 */
export const ALLOWED_QUERIES: Record<string, AllowedQuery> = {
  'business.summary': {
    name: 'Business Summary',
    description: 'Get basic business information (name, type, status, location)',
    endpoint: '/api/ai/business/{id}/summary',
    method: 'GET',
  },
  'employees.summary': {
    name: 'Employees Summary',
    description: 'Get aggregated employee data (counts, departments, average salary)',
    endpoint: '/api/ai/business/{id}/employees/summary',
    method: 'GET',
  },
  'payments.summary': {
    name: 'Payments Summary',
    description: 'Get payment schedule information and recent payment history',
    endpoint: '/api/ai/business/{id}/payments/summary',
    method: 'GET',
  },
  'payroll.summary': {
    name: 'Payroll Summary',
    description: 'Get payroll schedule information and recent payroll history',
    endpoint: '/api/ai/business/{id}/payroll/summary',
    method: 'GET',
  },
  'escrow.balance': {
    name: 'Escrow Balance',
    description: 'Get current escrow balance and upcoming obligations',
    endpoint: '/api/ai/business/{id}/escrow/balance',
    method: 'GET',
  },
  'compliance.status': {
    name: 'Compliance Status',
    description: 'Get tax compliance status (UI-19, EMP201, IRP5)',
    endpoint: '/api/ai/business/{id}/compliance/status',
    method: 'GET',
  },
  'context.full': {
    name: 'Full Context',
    description: 'Get complete business context in one request',
    endpoint: '/api/ai/business/{id}/context',
    method: 'GET',
  },
};

/**
 * Build endpoint URL with business ID substitution.
 */
export function buildEndpoint(queryKey: string, businessId: number): string | null {
  const query = ALLOWED_QUERIES[queryKey];
  if (!query) {
    console.warn(`Attempted to use unknown query: ${queryKey}`);
    return null;
  }

  return query.endpoint.replace('{id}', String(businessId));
}

/**
 * Check if a query key is allowed.
 */
export function isAllowedQuery(queryKey: string): boolean {
  return queryKey in ALLOWED_QUERIES;
}

/**
 * Get list of all allowed queries (for documentation/debugging).
 */
export function getAllowedQueries(): AllowedQuery[] {
  return Object.values(ALLOWED_QUERIES);
}

/**
 * Sensitive fields that should NEVER be exposed.
 * This is a safety check in case the main app accidentally returns sensitive data.
 */
export const SENSITIVE_FIELDS = [
  'password',
  'bank_account_number',
  'account_number',
  'id_number',
  'tax_number',
  'api_key',
  'secret',
  'token',
  'two_factor_secret',
  'two_factor_recovery_codes',
];

/**
 * Sanitize response data by removing any sensitive fields.
 */
export function sanitizeResponse(data: unknown): unknown {
  if (data === null || data === undefined) {
    return data;
  }

  if (Array.isArray(data)) {
    return data.map(sanitizeResponse);
  }

  if (typeof data === 'object') {
    const sanitized: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(data as Record<string, unknown>)) {
      // Skip sensitive fields
      if (SENSITIVE_FIELDS.some(field => key.toLowerCase().includes(field.toLowerCase()))) {
        continue;
      }
      sanitized[key] = sanitizeResponse(value);
    }
    return sanitized;
  }

  return data;
}
