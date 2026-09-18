# Stage 1: Build the React application
FROM node:20-alpine AS builder

WORKDIR /app

# Install dependencies
COPY package.json package-lock.json* ./
RUN npm install

# Copy application source code
COPY . .

# Build production static bundle
RUN npm run build

# Stage 2: Serve static files with Nginx
FROM nginx:alpine

# Copy built static assets to Nginx standard HTML directory
COPY --from=builder /app/dist /usr/share/nginx/html

# Copy Nginx template to official template directory for startup substitution
COPY nginx.conf /etc/nginx/templates/default.conf.template

# Restrict envsubst strictly to BACKEND_UPSTREAM
# This guarantees that standard Nginx variables ($host, $uri, $remote_addr, etc.) remain intact
ENV NGINX_ENVSUBST_FILTER="BACKEND_UPSTREAM"

# Expose HTTP port
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
