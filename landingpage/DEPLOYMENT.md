# FUN MOMENT — Landing Page Coolify Deployment Guide

This guide describes how to deploy the **FUN MOMENT** landing page and support email service as a **new, dedicated application** in Coolify.

---

## 1. Architecture Overview

- **Service Type**: Standalone Containerized Web Application (React 19 SPA + Node.js Express server)
- **Email Delivery**: Server-side Nodemailer via Gmail SMTP (`smtp.gmail.com:587`)
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

### B. Generate Gmail App Password (SMTP_PASS)
Gmail requires an **App Password** for SMTP authentication:
1. Log in to the support Google account: `funmomentsproject@gmail.com`.
2. Go to **Google Account Settings > Security**.
3. Under **How you sign in to Google**, ensure **2-Step Verification** is enabled.
4. Search for or open **App passwords** (`https://myaccount.google.com/apppasswords`).
5. Create a new App Password:
   - App Name: `FUN MOMENT Coolify Website`
6. Copy the generated **16-character password** (e.g., `abcd efgh ijkl mnop`).
7. Keep this secret safe. You will enter it directly in Coolify. **Do NOT commit it to Git.**

---

## 3. Create New Application in Coolify

> ⚠️ **IMPORTANT**: Do NOT add this to your existing Laravel or Admin application. Create it as a new resource.

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

Navigate to the **Environment Variables** tab of your new application in Coolify and configure the following variables:

| Variable | Value | Secret? | Description |
|:---|:---|:---:|:---|
| `PORT` | `3000` | No | Internal port listened to by Express |
| `NODE_ENV` | `production` | No | Production mode flag |
| `PUBLIC_BASE_URL` | `https://funmoments.unifinitylab.com` | No | Canonical public website domain |
| `SUPPORT_TO_EMAIL` | `funmomentsproject@gmail.com` | No | Support mailbox that receives inquiries |
| `SMTP_HOST` | `smtp.gmail.com` | No | Gmail SMTP host |
| `SMTP_PORT` | `587` | No | TLS submission port |
| `SMTP_SECURE` | `false` | No | `false` for STARTTLS on port 587 |
| `SMTP_USER` | `funmomentsproject@gmail.com` | No | Authenticated Gmail address |
| `SMTP_PASS` | `[16-character App Password]` | **YES** | **Secret**: Paste Gmail App Password without spaces |

> 🔒 **Security Note**: Check the **Is Secret / Sensitive** checkbox for `SMTP_PASS` in Coolify so it is masked in logs and build outputs.

---

## 6. Deployment & Post-Deploy Verification

1. Click **Deploy**.
2. Coolify will:
   - Clone the repository at branch `fix/provider-signup-otp`.
   - Build the frontend bundle using Bun inside the Docker builder stage.
   - Assemble the lightweight Node.js 20 production runner image.
   - Start the container and verify `GET /healthz`.
   - Coolify Traefik reverse proxy will automatically obtain a Let's Encrypt SSL certificate for `https://funmoments.unifinitylab.com`.
3. **Verify the Live Website**:
   - Open `https://funmoments.unifinitylab.com/` in your browser.
   - Verify Arabic RTL and English LTR display.
   - Test direct routes:
     - `https://funmoments.unifinitylab.com/privacy`
     - `https://funmoments.unifinitylab.com/support`
   - Test Support Form Submission:
     - Submit a test inquiry through `https://funmoments.unifinitylab.com/support`.
     - Check `funmomentsproject@gmail.com` inbox for the formatted notification email.
     - Confirm that replying to the received email directs to the submitter's email address (`Reply-To`).
