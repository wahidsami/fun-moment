# FUN MOMENT — Landing Page Coolify Deployment Guide (Web3Forms)

This guide describes how to deploy the **FUN MOMENT** landing page and support inquiry service as a **new, dedicated application** in Coolify using direct **Web3Forms** integration.

---

## 1. Architecture Overview

- **Service Type**: Standalone Containerized Web Application (React 19 SPA + Node.js Express server)
- **Form Delivery**: Direct browser-to-API delivery via **Web3Forms** (`https://api.web3forms.com/submit`)
  > *Why Web3Forms instead of SMTP? Most VPS providers (including Contabo, DigitalOcean, Hetzner, AWS) block or throttle outgoing SMTP traffic on ports 25, 465, and 587 by default. Web3Forms submits directly from the user's browser over standard HTTPS (Port 443), completely eliminating VPS network timeouts and delivering inquiries directly to `funmomentsproject@gmail.com`.*
- **Isolation**: Completely separate Coolify resource. Does not modify or replace the Laravel API, admin dashboard, or PostgreSQL databases.
- **Internal Port**: `3000`
- **Healthcheck Endpoint**: `GET /healthz`
- **Public URL**: `https://funmoments.unifinitylab.com`

---

## 2. Prerequisites & DNS Configuration

### A. Point Subdomain to the Coolify VPS
1. Open your DNS provider (e.g. Cloudflare, Namecheap, GoDaddy).
2. Create an **A Record**:
   - **Type**: `A`
   - **Name / Host**: `funmoments` (for `funmoments.unifinitylab.com`)
   - **Value / IP**: Your Coolify VPS Public IP address.
     > *To find your VPS IP: In Coolify, navigate to **Servers > localhost (or your server name)** and copy the Server IP address shown on the dashboard.*
   - **TTL**: Auto or 300 seconds.
   - **Proxy**: If using Cloudflare, set proxy mode to DNS-only during initial Let's Encrypt certificate issuance, then proxy if desired.

### B. Web3Forms Form Access Key
1. The Web3Forms access key is already generated for `funmomentsproject@gmail.com`.
2. Keep this key handy. You will configure it in Coolify as a **Build-time variable**.
3. *Note*: Web3Forms access keys are public form tokens designed for client-side forms (not passwords). They are embedded in the client bundle by design.

---

## 3. Create New Application in Coolify

> ⚠️ **IMPORTANT**: Do NOT add this to your existing Laravel or Admin application. Create it as a new separate resource.

1. In your Coolify dashboard, select your **Project** and **Environment** (e.g., `Production`).
2. Click **+ Add Resource / New Application**.
3. Select **Public Repository** or **Private Repository (GitHub App / Deploy Key)**:
   - **Repository URL**: `https://github.com/wahidsami/fun-moment.git`
4. Set the **Branch**:
   - `fix/provider-signup-otp`
5. Select **Build Pack**:
   - Choose **Dockerfile**.

---

## 4. Critical Path & Build Configuration

Coolify allows specifying a **Base Directory** and a **Dockerfile Location**. Ensure you enter the exact settings below to avoid double-path resolution issues:

### Recommended Setting (Base Directory Scoped):
- **Base Directory**: `/landingpage`
- **Dockerfile Location**: `/Dockerfile`

*(Explanation: Setting Base Directory to `/landingpage` scopes the build context to the website directory only, and looks for `Dockerfile` directly inside `landingpage/` without building the entire monorepo).*

### Alternative Setting (Root Scoped):
- **Base Directory**: `/`
- **Dockerfile Location**: `/landingpage/Dockerfile`

### General Application Settings:
- **Exposed Port**: `3000`
- **Domains**: `https://funmoments.unifinitylab.com`
- **Healthcheck Path**: `/healthz`

---

## 5. Environment Variables Configuration

Navigate to the **Environment Variables** tab of your new application in Coolify:

| Variable | Value | Available at Build Time? | Description |
|:---|:---|:---:|:---|
| `VITE_WEB3FORMS_ACCESS_KEY` | `[Your-Web3Forms-Key]` | **YES (CRITICAL)** | **Must be checked as "Available at build time" / "Build variable"** so Vite embeds it into the static bundle during `bun run build`. |
| `PORT` | `3000` | No | Internal port listened to by Express |
| `NODE_ENV` | `production` | No | Production mode flag |
| `PUBLIC_BASE_URL` | `https://funmoments.unifinitylab.com` | No | Canonical public website domain |

> ⚠️ **CRITICAL NOTE ON `VITE_WEB3FORMS_ACCESS_KEY`**:
> Because the landing page is a Vite Single-Page Application (SPA), all `VITE_*` variables are embedded into client JavaScript files during the **build stage**.
> In Coolify:
> 1. Add variable name: `VITE_WEB3FORMS_ACCESS_KEY`
> 2. Paste your access key.
> 3. Check the checkbox: **"Is build time variable?"** (or **"Available at build time"**).
> 4. Save changes.

---

## 6. Deployment & Post-Deploy Verification

1. Click **Deploy**.
2. Coolify will:
   - Clone the repository at branch `fix/provider-signup-otp`.
   - Pass `VITE_WEB3FORMS_ACCESS_KEY` as a build argument into the Dockerfile.
   - Build the frontend bundle using Bun inside the Docker builder stage.
   - Assemble the lightweight Node.js 20 production runner image.
   - Start the container and verify `GET /healthz`.
   - Provision Let's Encrypt SSL automatically for `https://funmoments.unifinitylab.com`.
3. **Verify the Live Website**:
   - Open `https://funmoments.unifinitylab.com/` in your browser.
   - Verify Arabic RTL and English LTR display.
   - Test direct routes:
     - `https://funmoments.unifinitylab.com/privacy`
     - `https://funmoments.unifinitylab.com/support`
   - Test Support Form Submission:
     - Submit a test inquiry through `https://funmoments.unifinitylab.com/support`.
     - Check `funmomentsproject@gmail.com` inbox for the Web3Forms notification email.
     - Confirm that replying to the received email directs to the submitter's email address (`Reply-To`).
