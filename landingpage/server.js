/**
 * FUN MOMENT — Production Web Server
 * Serves compiled React 19 SPA and provides container health check endpoint.
 * Note: Support form submissions are handled directly in the browser via Web3Forms.
 */

import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';
import dotenv from 'dotenv';
import fs from 'fs';

// Load environment variables from .env if present
dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();

// Configuration
const PORT = parseInt(process.env.PORT || '3000', 10);
const HOST = '0.0.0.0';
const NODE_ENV = process.env.NODE_ENV || 'production';

// Security headers
app.use((req, res, next) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'DENY');
  res.setHeader('X-XSS-Protection', '1; mode=block');
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
  next();
});

// -------------------------------------------------------------
// Health Check Endpoint (GET /healthz)
// Used by Coolify and Docker container healthchecks.
// -------------------------------------------------------------
app.get('/healthz', (req, res) => {
  res.status(200).json({
    status: 'ok',
    service: 'fun-moment-landingpage',
    timestamp: new Date().toISOString(),
  });
});

// -------------------------------------------------------------
// Legacy API endpoint note
// The website support form now submits directly to Web3Forms.
// -------------------------------------------------------------
app.all('/api/support', (req, res) => {
  res.status(410).json({
    success: false,
    message: 'Support inquiries are now submitted directly via Web3Forms.',
  });
});

// -------------------------------------------------------------
// Serve Static Vite Frontend & SPA Fallback Routing
// -------------------------------------------------------------
const distPath = path.join(__dirname, 'dist');

if (fs.existsSync(distPath)) {
  // Serve static assets with caching
  app.use(
    express.static(distPath, {
      maxAge: '1d',
      index: false,
    })
  );

  // SPA fallback for all non-API GET routes (including /, /privacy, /support)
  app.get('*', (req, res) => {
    // Never intercept API routes with SPA fallback
    if (req.path.startsWith('/api/') || req.path === '/healthz') {
      return res.status(404).json({ error: 'Endpoint not found' });
    }
    res.sendFile(path.join(distPath, 'index.html'));
  });
} else {
  // Helpful warning if dist directory hasn't been built yet
  app.get('*', (req, res) => {
    if (req.path.startsWith('/api/') || req.path === '/healthz') {
      return res.status(404).json({ error: 'Endpoint not found' });
    }
    res.status(503).send('Application frontend is building or dist/ not found. Run "npm run build".');
  });
}

// Start Server (only when not running automated tests)
if (process.env.NODE_ENV !== 'test') {
  app.listen(PORT, HOST, () => {
    console.log(`[FUN MOMENT Landing] Server listening on http://${HOST}:${PORT} (${NODE_ENV})`);
  });
}

export default app;
