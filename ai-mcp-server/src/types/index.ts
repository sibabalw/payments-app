export interface ChatRequest {
  business_id: number;
  message: string;
  conversation_history?: ConversationMessage[];
}

export interface ConversationMessage {
  role: 'user' | 'assistant';
  content: string;
}

export interface ChatResponse {
  content: string;
  metadata?: {
    model: string;
    usage?: {
      prompt_tokens: number;
      completion_tokens: number;
      total_tokens: number;
    };
  };
}

export interface BusinessContext {
  business: BusinessSummary;
  employees: EmployeesSummary;
  payments: PaymentsSummary;
  payroll: PayrollSummary;
  escrow: EscrowBalance;
  compliance: ComplianceStatus;
}

export interface BusinessSummary {
  id: number;
  name: string;
  business_type: string | null;
  status: string;
  city: string | null;
  province: string | null;
  country: string;
  created_at: string;
}

export interface EmployeesSummary {
  total_count: number;
  departments: Record<string, number>;
  employment_types: Record<string, number>;
  average_salary: number;
  total_monthly_payroll: number;
}

export interface PaymentsSummary {
  total_schedules: number;
  schedule_statuses: Record<string, number>;
  upcoming_payments: UpcomingPayment[];
  recent_jobs_30_days: {
    total: number;
    by_status: Record<string, number>;
    total_amount: number;
  };
}

export interface UpcomingPayment {
  name: string;
  amount: number;
  currency: string;
  next_run_at: string | null;
  frequency: string;
}

export interface PayrollSummary {
  total_schedules: number;
  schedule_statuses: Record<string, number>;
  upcoming_payroll: UpcomingPayroll[];
  recent_jobs_30_days: {
    total: number;
    by_status: Record<string, number>;
    total_gross: number;
    total_net: number;
    total_paye: number;
    total_uif: number;
  };
}

export interface UpcomingPayroll {
  name: string;
  next_run_at: string | null;
  frequency: string;
  employee_count: number;
}

export interface EscrowBalance {
  current_balance: number;
  currency: string;
  upcoming_obligations_7_days: {
    payments: number;
    payroll: number;
    total: number;
  };
  is_sufficient: boolean;
}

export interface ComplianceStatus {
  current_month: string;
  current_tax_year: string;
  ui19: {
    status: string;
    submitted_at: string | null;
  };
  emp201: {
    status: string;
    submitted_at: string | null;
  };
  irp5: {
    generated_count: number;
    total_employees: number;
    completion_percentage: number;
  };
}
