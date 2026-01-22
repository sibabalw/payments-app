import type { BusinessContext } from '../types/index.js';

const MAIN_APP_URL = process.env.MAIN_APP_URL || 'http://localhost:8000';
const MAIN_APP_API_KEY = process.env.MAIN_APP_API_KEY || '';

/**
 * Fetches business context from the main Swift Pay application.
 * This service only has access to read-only, aggregated data endpoints.
 */
export class BusinessContextService {
  private baseUrl: string;
  private apiKey: string;

  constructor() {
    this.baseUrl = MAIN_APP_URL;
    this.apiKey = MAIN_APP_API_KEY;
  }

  /**
   * Fetch full business context for AI queries.
   */
  async getFullContext(businessId: number): Promise<BusinessContext> {
    const response = await this.fetchFromMainApp(`/api/ai/business/${businessId}/context`);
    return response as BusinessContext;
  }

  /**
   * Fetch individual context components (for more granular control).
   */
  async getBusinessSummary(businessId: number) {
    return this.fetchFromMainApp(`/api/ai/business/${businessId}/summary`);
  }

  async getEmployeesSummary(businessId: number) {
    return this.fetchFromMainApp(`/api/ai/business/${businessId}/employees/summary`);
  }

  async getPaymentsSummary(businessId: number) {
    return this.fetchFromMainApp(`/api/ai/business/${businessId}/payments/summary`);
  }

  async getPayrollSummary(businessId: number) {
    return this.fetchFromMainApp(`/api/ai/business/${businessId}/payroll/summary`);
  }

  async getEscrowBalance(businessId: number) {
    return this.fetchFromMainApp(`/api/ai/business/${businessId}/escrow/balance`);
  }

  async getComplianceStatus(businessId: number) {
    return this.fetchFromMainApp(`/api/ai/business/${businessId}/compliance/status`);
  }

  /**
   * Generic fetch method with authentication and error handling.
   */
  private async fetchFromMainApp(endpoint: string): Promise<unknown> {
    const url = `${this.baseUrl}${endpoint}`;

    try {
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'X-AI-Server-Key': this.apiKey,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      });

      if (!response.ok) {
        const errorText = await response.text();
        console.error(`Main app request failed: ${response.status}`, errorText);
        throw new Error(`Failed to fetch from main app: ${response.status}`);
      }

      return response.json();
    } catch (error) {
      console.error(`Error fetching from main app (${endpoint}):`, error);
      throw error;
    }
  }
}
