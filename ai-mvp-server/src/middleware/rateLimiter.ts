import rateLimit from 'express-rate-limit';

export const chatRateLimiter = rateLimit({
  windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS || '60000', 10), // 1 minute
  max: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS || '20', 10), // 20 requests per minute
  message: {
    error: 'Too Many Requests',
    message: 'You have exceeded the rate limit. Please try again later.',
  },
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: (req) => {
    // Use business_id from request body for rate limiting
    const businessId = req.body?.business_id;
    return businessId ? `business_${businessId}` : req.ip || 'unknown';
  },
});
