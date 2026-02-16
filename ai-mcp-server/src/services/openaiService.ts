import OpenAI from 'openai';
import type { BusinessContext, ConversationMessage } from '../types/index.js';

const SYSTEM_PROMPT = `You are an AI assistant for SwiftPay, a South African payments and payroll management system.

You help business owners understand their business data. You can answer questions about:
- Business information and status
- Employee counts and department breakdowns
- Upcoming payments and payment schedules
- Payroll summaries and schedules
- Escrow account balance
- Tax compliance status (UIF, EMP201, IRP5)

You CANNOT:
- Make any changes to data
- Access sensitive information (bank details, passwords, ID numbers)
- Execute any database queries directly
- Perform any actions on behalf of the user

Always be helpful, concise, and accurate. If you don't have the information to answer a question, say so clearly.

Format currency values in South African Rand (R) with proper formatting.
When providing numbers, use appropriate formatting for readability.

Current business context will be provided with each query.`;

export class OpenAIService {
  private client: OpenAI;
  private model: string;

  constructor() {
    const apiKey = process.env.OPENAI_API_KEY;
    if (!apiKey) {
      throw new Error('OPENAI_API_KEY is not configured');
    }

    this.client = new OpenAI({ apiKey });
    this.model = process.env.OPENAI_MODEL || 'gpt-4o';
  }

  async chat(
    message: string,
    context: BusinessContext,
    conversationHistory: ConversationMessage[] = []
  ): Promise<{ content: string; metadata: { model: string; usage?: OpenAI.Completions.CompletionUsage } }> {
    const contextMessage = this.formatContextMessage(context);

    const messages: OpenAI.Chat.ChatCompletionMessageParam[] = [
      { role: 'system', content: SYSTEM_PROMPT },
      { role: 'system', content: contextMessage },
      ...conversationHistory.map((msg) => ({
        role: msg.role as 'user' | 'assistant',
        content: msg.content,
      })),
      { role: 'user', content: message },
    ];

    try {
      const completion = await this.client.chat.completions.create({
        model: this.model,
        messages,
        temperature: 0.7,
        max_tokens: 1000,
      });

      const responseContent = completion.choices[0]?.message?.content || 'I could not generate a response.';

      return {
        content: responseContent,
        metadata: {
          model: completion.model,
          usage: completion.usage ?? undefined,
        },
      };
    } catch (error) {
      console.error('OpenAI API error:', error);
      throw new Error('Failed to get response from AI service');
    }
  }

  private formatContextMessage(context: BusinessContext): string {
    const { business, employees, payments, payroll, escrow, compliance } = context;

    return `Current Business Context:

BUSINESS INFORMATION:
- Name: ${business.name}
- Type: ${business.business_type || 'Not specified'}
- Status: ${business.status}
- Location: ${[business.city, business.province, business.country].filter(Boolean).join(', ')}
- Created: ${business.created_at}

EMPLOYEES:
- Total Employees: ${employees.total_count}
- Departments: ${Object.entries(employees.departments).map(([dept, count]) => `${dept || 'Unassigned'}: ${count}`).join(', ') || 'None'}
- Employment Types: ${Object.entries(employees.employment_types).map(([type, count]) => `${type}: ${count}`).join(', ') || 'None'}
- Average Salary: R ${employees.average_salary.toLocaleString('en-ZA', { minimumFractionDigits: 2 })}
- Total Monthly Payroll: R ${employees.total_monthly_payroll.toLocaleString('en-ZA', { minimumFractionDigits: 2 })}

PAYMENT SCHEDULES:
- Total Schedules: ${payments.total_schedules}
- Status Breakdown: ${Object.entries(payments.schedule_statuses).map(([status, count]) => `${status}: ${count}`).join(', ') || 'None'}
- Upcoming Payments: ${payments.upcoming_payments.length > 0 ? payments.upcoming_payments.map(p => `${p.name} (R ${p.amount.toLocaleString('en-ZA', { minimumFractionDigits: 2 })} on ${p.next_run_at})`).join('; ') : 'None scheduled'}
- Last 30 Days: ${payments.recent_jobs_30_days.total} jobs, R ${payments.recent_jobs_30_days.total_amount.toLocaleString('en-ZA', { minimumFractionDigits: 2 })} total

PAYROLL:
- Total Schedules: ${payroll.total_schedules}
- Status Breakdown: ${Object.entries(payroll.schedule_statuses).map(([status, count]) => `${status}: ${count}`).join(', ') || 'None'}
- Upcoming Payroll: ${payroll.upcoming_payroll.length > 0 ? payroll.upcoming_payroll.map(p => `${p.name} (${p.employee_count} employees on ${p.next_run_at})`).join('; ') : 'None scheduled'}
- Last 30 Days Stats:
  - Jobs: ${payroll.recent_jobs_30_days.total}
  - Gross: R ${payroll.recent_jobs_30_days.total_gross.toLocaleString('en-ZA', { minimumFractionDigits: 2 })}
  - Net: R ${payroll.recent_jobs_30_days.total_net.toLocaleString('en-ZA', { minimumFractionDigits: 2 })}
  - PAYE: R ${payroll.recent_jobs_30_days.total_paye.toLocaleString('en-ZA', { minimumFractionDigits: 2 })}
  - UIF: R ${payroll.recent_jobs_30_days.total_uif.toLocaleString('en-ZA', { minimumFractionDigits: 2 })}

ESCROW BALANCE:
- Current Balance: R ${escrow.current_balance.toLocaleString('en-ZA', { minimumFractionDigits: 2 })}
- Upcoming Obligations (7 days):
  - Payments: R ${escrow.upcoming_obligations_7_days.payments.toLocaleString('en-ZA', { minimumFractionDigits: 2 })}
  - Payroll: R ${escrow.upcoming_obligations_7_days.payroll.toLocaleString('en-ZA', { minimumFractionDigits: 2 })}
  - Total: R ${escrow.upcoming_obligations_7_days.total.toLocaleString('en-ZA', { minimumFractionDigits: 2 })}
- Sufficient Funds: ${escrow.is_sufficient ? 'Yes' : 'No - needs attention'}

COMPLIANCE STATUS:
- Current Month: ${compliance.current_month}
- Tax Year: ${compliance.current_tax_year}
- UI-19: ${compliance.ui19.status}${compliance.ui19.submitted_at ? ` (submitted: ${compliance.ui19.submitted_at})` : ''}
- EMP201: ${compliance.emp201.status}${compliance.emp201.submitted_at ? ` (submitted: ${compliance.emp201.submitted_at})` : ''}
- IRP5: ${compliance.irp5.generated_count}/${compliance.irp5.total_employees} employees (${compliance.irp5.completion_percentage}% complete)`;
  }
}
