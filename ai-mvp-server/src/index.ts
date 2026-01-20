import cors from 'cors';
import dotenv from 'dotenv';
import express from 'express';
import helmet from 'helmet';

import { chatHandler, healthHandler } from './controllers/aiController.js';
import { authMiddleware } from './middleware/authMiddleware.js';
import { chatRateLimiter } from './middleware/rateLimiter.js';

// Load environment variables
dotenv.config();

const app = express();
const PORT = process.env.PORT || 3001;

// Middleware
app.use(helmet());
app.use(cors({
  origin: process.env.MAIN_APP_URL || 'http://localhost:8000',
  credentials: true,
}));
app.use(express.json());

// Health check endpoint (no auth required)
app.get('/health', healthHandler);

// Chat endpoint (requires auth and rate limiting)
app.post('/api/chat', authMiddleware, chatRateLimiter, chatHandler);

// Error handling middleware
app.use((err: Error, req: express.Request, res: express.Response, _next: express.NextFunction) => {
  console.error('Unhandled error:', err);
  res.status(500).json({
    error: 'Internal Server Error',
    message: process.env.NODE_ENV === 'development' ? err.message : 'An unexpected error occurred',
  });
});

// 404 handler
app.use((req: express.Request, res: express.Response) => {
  res.status(404).json({
    error: 'Not Found',
    message: `Route ${req.method} ${req.path} not found`,
  });
});

// Start server
app.listen(PORT, () => {
  console.log(`AI MVP Server running on port ${PORT}`);
  console.log(`Environment: ${process.env.NODE_ENV || 'development'}`);
  console.log(`Main App URL: ${process.env.MAIN_APP_URL || 'http://localhost:8000'}`);
});

export default app;
