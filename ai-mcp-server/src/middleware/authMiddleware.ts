import { NextFunction, Request, Response } from 'express';

export function authMiddleware(req: Request, res: Response, next: NextFunction): void {
  const apiKey = req.headers['x-ai-server-key'] as string | undefined;
  const authHeader = req.headers['authorization'] as string | undefined;

  let providedKey = apiKey;

  // Check Authorization header with Bearer prefix
  if (!providedKey && authHeader?.startsWith('Bearer ')) {
    providedKey = authHeader.slice(7);
  }

  const expectedKey = process.env.MAIN_APP_API_KEY;

  if (!expectedKey) {
    console.error('MAIN_APP_API_KEY not configured');
    res.status(500).json({ error: 'Server configuration error' });
    return;
  }

  if (!providedKey || providedKey !== expectedKey) {
    res.status(401).json({
      error: 'Unauthorized',
      message: 'Invalid or missing API key',
    });
    return;
  }

  next();
}
