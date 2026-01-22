import { Request, Response } from 'express';
import { z } from 'zod';
import { BusinessContextService } from '../services/businessContext.js';
import { OpenAIService } from '../services/openaiService.js';
import type { ChatRequest, ChatResponse } from '../types/index.js';

// Request validation schema
const chatRequestSchema = z.object({
  business_id: z.number().int().positive(),
  message: z.string().min(1).max(5000),
  conversation_history: z.array(z.object({
    role: z.enum(['user', 'assistant']),
    content: z.string(),
  })).optional().default([]),
});

let openaiService: OpenAIService | null = null;
let businessContextService: BusinessContextService | null = null;

/**
 * Initialize services lazily to allow for environment variable configuration.
 */
function getServices() {
  if (!openaiService) {
    openaiService = new OpenAIService();
  }
  if (!businessContextService) {
    businessContextService = new BusinessContextService();
  }
  return { openaiService, businessContextService };
}

/**
 * Health check handler.
 */
export function healthHandler(req: Request, res: Response): void {
  res.json({
    status: 'healthy',
    timestamp: new Date().toISOString(),
    version: '1.0.0',
  });
}

/**
 * Chat handler - processes user messages and returns AI responses.
 */
export async function chatHandler(req: Request, res: Response): Promise<void> {
  try {
    // Validate request body
    const validationResult = chatRequestSchema.safeParse(req.body);
    
    if (!validationResult.success) {
      res.status(400).json({
        error: 'Validation Error',
        message: 'Invalid request body',
        details: validationResult.error.errors,
      });
      return;
    }

    const { business_id, message, conversation_history } = validationResult.data as ChatRequest;

    const { openaiService, businessContextService } = getServices();

    // Fetch business context from main app
    let context;
    try {
      context = await businessContextService.getFullContext(business_id);
    } catch (error) {
      console.error('Failed to fetch business context:', error);
      res.status(502).json({
        error: 'Context Error',
        message: 'Failed to fetch business context. Please try again later.',
      });
      return;
    }

    // Get AI response
    let aiResponse;
    try {
      aiResponse = await openaiService.chat(message, context, conversation_history);
    } catch (error) {
      console.error('OpenAI API error:', error);
      res.status(502).json({
        error: 'AI Error',
        message: 'Failed to get AI response. Please try again later.',
      });
      return;
    }

    // Return response
    const response: ChatResponse = {
      content: aiResponse.content,
      metadata: aiResponse.metadata,
    };

    res.json(response);
  } catch (error) {
    console.error('Unexpected error in chat handler:', error);
    res.status(500).json({
      error: 'Internal Server Error',
      message: 'An unexpected error occurred',
    });
  }
}
