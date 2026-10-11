# =============================================================
# Stage 1: Build Frontend (React 19 + TypeScript + Vite + Tailwind v4)
# =============================================================
FROM oven/bun:1-alpine AS builder

WORKDIR /app

# Copy package definitions and lockfile
COPY package.json bun.lock ./

# Install all dependencies using Bun lockfile
RUN bun install --frozen-lockfile

# Copy website source and configuration
COPY . .

# Accept build-time environment variable for Vite client bundle
ARG VITE_WEB3FORMS_ACCESS_KEY
ENV VITE_WEB3FORMS_ACCESS_KEY=$VITE_WEB3FORMS_ACCESS_KEY

# Compile production SPA bundle into dist/
RUN bun run build

# =============================================================
# Stage 2: Production Server Runtime (Node.js 20 Alpine)
# =============================================================
FROM node:20-alpine AS runner

WORKDIR /app

# Install wget for container healthcheck
RUN apk add --no-cache wget

# Set production environment defaults
ENV NODE_ENV=production
ENV PORT=3000

# Copy package definition and install production runtime dependencies
COPY package.json ./
RUN npm install --omit=dev --legacy-peer-deps

# Copy compiled frontend from builder stage
COPY --from=builder /app/dist ./dist

# Copy production Express server
COPY server.js ./

# Expose internal service port
EXPOSE 3000

# Container healthcheck using GET /healthz
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
  CMD wget -qO- http://127.0.0.1:${PORT:-3000}/healthz || exit 1

# Start production server
CMD ["node", "server.js"]
