# ===========================================
# PARKFLOW DEPLOYMENT - FREE TIER SETUP
# ===========================================
# 
# ARCHITECTURE:
# - Frontend    → Vercel (FREE unlimited)
# - Backend     → AWS EC2 t3.micro (FREE 12 months)
# - Database    → AWS RDS PostgreSQL (FREE 12 months)
# - Cache/Redis → AWS ElastiCache (FREE tier)
# - Storage     → AWS S3 (FREE 5GB)
#
# TOTAL COST YEAR 1: $0 (after AWS free tier)
# ===========================================

## STEP 1: SETUP AWS ACCOUNT

### 1.1 Create AWS Account
```
1. Buka https://aws.amazon.com/
2. Klik "Create an AWS Account"
3. Pilih "Personal" account
4. Fill in:
   - Email: your-email@gmail.com
   - Password: Create strong password
   - Full name: Wahyu Purnama
   - Phone: +62xxxxxxxxxxx
   - Country: Indonesia
5. Add Payment Method (Kartu kredit/debit)
6. Verify via phone
7. Complete identity verification
```

### 1.2 Enable Free Tier Alerts
```
1. Go to AWS Console → My Account → Billing Dashboard
2. Klik "Billing preferences"
3. Enable:
   - Receive Free Tier Usage Alerts
   - Receive Billing Alerts
4. Set threshold: $0.01 (to get notified early)
```

---

## STEP 2: SETUP AWS RDS POSTGRESQL (FREE TIER)

### 2.1 Create RDS Instance
```
1. Go to AWS Console → Services → RDS
2. Klik "Create database"
3. Choose options:
   - Creation method: Standard create
   - Engine type: PostgreSQL
   - Version: PostgreSQL 17.4 or latest
   - Templates: Free tier ✅
   - DB instance identifier: parkflow-db
   - Master username: parkflow
   - Master password: [Create strong password - SAVE THIS!]
   - Confirm password: [Same password]
   
4. Instance configuration:
   - DB instance class: db.t3.micro ✅ (Free tier)
   
5. Storage:
   - Allocated storage: 20GB ✅ (Free tier)
   - Storage autoscaling: Disable
   
6. Connectivity:
   - Compute resource: Don't connect to EC2
   - VPC: Default VPC
   - Subnet group: default
   - Public access: Yes (for EC2 access)
   - VPC security group: Create new
     Name: parkflow-sg
   - Additional configuration:
     - Port: 5432
   
7. Database authentication:
   - Password authentication: ✅
   
8. Monitoring:
   - Enable Performance Insights: Disable (free tier)
   - Enable Free tier monitoring: ✅
   
9. Click "Create database"
```

### 2.2 Wait for RDS to be Available
```
⏱️ Time: 5-10 minutes
Status: Available (green)
```

### 2.3 Get RDS Endpoint
```
1. Go to RDS → Databases → parkflow-db
2. Copy endpoint: 
   parkflow-db.xxxxx.us-east-1.rds.amazonaws.com
3. Save this for later!
```

### 2.4 Configure Security Group (Allow EC2)
```
1. Go to EC2 → Security Groups
2. Find "parkflow-sg"
3. Edit inbound rules:
   - Type: PostgreSQL
   - Source: Custom → Select your future EC2 security group
   - Or: Anywhere (0.0.0.0/0) for development
4. Save rules
```

---

## STEP 3: SETUP AWS ELASTICACHE REDIS (FREE TIER)

### 3.1 Create ElastiCache Redis
```
1. Go to AWS Console → ElastiCache
2. Click "Get started"
3. Choose engine: Redis
4. Deployment options: CloudFormation
5. Cluster mode: Disabled (production)
6. Cluster info:
   - Name: parkflow-redis
   - Engine version: 7.x
   
7. Node settings:
   - Node type: cache.t3.micro ✅ (Free tier)
   - Number of replicas: 0
   
8. Subnet group:
   - Create new: parkflow-redis-subnet
   
9. Security:
   - VPC security groups: parkflow-sg
   
10. Backup:
    - Automatic backups: Disable (save resources)
    
11. Click "Create"
```

### 3.2 Get Redis Endpoint
```
⏱️ Time: 3-5 minutes
1. Go to ElastiCache → Redis → parkflow-redis
2. Copy endpoint:
   parkflow-redis.xxxxx.clustercfg.us-east1.cache.amazonaws.com
```

---

## STEP 4: SETUP AWS EC2 (FREE TIER)

### 4.1 Launch EC2 Instance
```
1. Go to AWS Console → EC2
2. Click "Instances" → "Launch instances"
3. Name: parkflow-server
4. OS: Ubuntu Server 24.04 LTS (Free tier eligible) ✅
5. Instance type: t3.micro ✅ (Free tier)

6. Key pair:
   - Create new key pair
   - Name: parkflow-key
   - Type: RSA
   - Format: .pem (for SSH)
   - Click "Create"
   - DOWNLOAD THE .pem FILE! (You can't download again)
   
7. Network settings:
   - VPC: Default VPC
   - Subnet: Any availability zone
   - Auto-assign public IP: Enable ✅
   - Firewall (security group): 
     Create security group: parkflow-ec2-sg
     - SSH (22): My IP
     - HTTP (80): Anywhere
     - HTTPS (443): Anywhere
     - Custom TCP (8443): Anywhere (for backend)
     
8. Configure storage:
   - Root volume: 8GB gp3 ✅ (Free tier)
   
9. Advanced details:
   - IAM instance profile: None
   - User data: (We'll add a startup script)
   
10. Click "Launch instance"
```

### 4.2 Wait for EC2 to be Ready
```
⏱️ Time: 2-3 minutes
Status: Running (green)
```

### 4.3 Get EC2 Public IP
```
1. Go to EC2 → Instances → parkflow-server
2. Copy Public IPv4 address: 54.xxx.xxx.xxx
3. Save this IP address!
```

### 4.4 Connect to EC2 via SSH
```bash
# Open terminal, go to folder where you saved the .pem file
cd ~/Downloads

# Set permissions
chmod 400 parkflow-key.pem

# Connect
ssh -i "parkflow-key.pem" ubuntu@54.xxx.xxx.xxx
```

---

## STEP 5: SETUP EC2 SERVER (AUTOMATIC SCRIPT)

### 5.1 Run Automatic Setup Script
Copy and paste this entire script to SSH terminal:

```bash
#!/bin/bash
set -e

echo "🚀 Starting ParkFlow Server Setup..."

# Update system
echo "📦 Updating system packages..."
sudo apt update && sudo apt upgrade -y

# Install Docker
echo "🐳 Installing Docker..."
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker ubuntu

# Install Docker Compose
echo "🔧 Installing Docker Compose..."
sudo curl -L "https://github.com/docker/compose/releases/download/v2.24.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Install Nginx
echo "🌐 Installing Nginx..."
sudo apt install -y nginx

# Install Certbot (for SSL)
echo "🔒 Installing Certbot..."
sudo apt install -y certbot python3-certbot-nginx

# Create ParkFlow directory
echo "📁 Creating ParkFlow directory..."
sudo mkdir -p /var/www/parkflow
sudo chown -R ubuntu:ubuntu /var/www/parkflow

# Setup Firewall
echo "🔥 Configuring Firewall..."
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 8443/tcp
sudo ufw --force enable

# Setup Docker to start on boot
echo "⚡ Enabling Docker service..."
sudo systemctl enable docker
sudo systemctl enable containerd

# Install Git
echo "📚 Installing Git..."
sudo apt install -y git

echo "✅ Setup complete! Rebooting..."
sudo reboot
```

Wait for reboot (30 seconds), then reconnect:

```bash
# Reconnect after reboot
ssh -i "parkflow-key.pem" ubuntu@54.xxx.xxx.xxx
```

### 5.2 Deploy ParkFlow Backend
```bash
# Clone your repository
cd /var/www/parkflow
git clone https://github.com/wahyupurnamaaa/parkflow.git .

# Navigate to backend
cd backend

# Create .env file
cat > .env << 'EOF'
APP_NAME=ParkFlow
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://parkflow.wahyupurnamaa.com

DB_CONNECTION=pgsql
DB_HOST=YOUR_RDS_ENDPOINT
DB_PORT=5432
DB_DATABASE=parkflow
DB_USERNAME=parkflow
DB_PASSWORD=YOUR_RDS_PASSWORD

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis

REDIS_HOST=YOUR_ELASTICACHE_ENDPOINT
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_CLIENT=phpredis

LOG_CHANNEL=stderr
DEMO_SEED=false
EOF

# Generate APP_KEY
php artisan key:generate --force

# Build and start containers
docker compose up -d --build

# Check status
docker compose ps

# Run migrations
docker compose exec backend php artisan migrate --force

# Create admin user
docker compose exec backend php artisan make:admin admin@parkflow.com ParkFlow123!

echo "✅ Backend deployed!"
```

---

## STEP 6: SETUP NGINX REVERSE PROXY (SSL)

### 6.1 Create Nginx Config
```bash
sudo nano /etc/nginx/sites-available/parkflow
```

Paste this configuration:

```nginx
# HTTP -> HTTPS redirect
server {
    listen 80;
    server_name parkflow.wahyupurnamaa.com;
    return 301 https://$server_name$request_uri;
}

# HTTPS Backend
server {
    listen 443 ssl http2;
    server_name parkflow.wahyupurnamaa.com;

    ssl_certificate /etc/letsencrypt/live/parkflow.wahyupurnamaa.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/parkflow.wahyupurnamaa.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;

    # Backend API
    location /api/ {
        proxy_pass http://127.0.0.1:8443/api/;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_set_header X-CSRF-Token $http_x_csrf_token;
        proxy_set_header Cookie $http_cookie;
        proxy_pass_header Set-Cookie;
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # Health check
    location /health {
        access_log off;
        return 200 "OK";
    }
}
```

### 6.2 Enable Site
```bash
sudo ln -s /etc/nginx/sites-available/parkflow /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default  # Remove default
sudo nginx -t  # Test config
sudo systemctl reload nginx
```

### 6.3 Get SSL Certificate
```bash
# Stop nginx temporarily
sudo systemctl stop nginx

# Get SSL certificate
sudo certbot certonly --standalone -d parkflow.wahyupurnamaa.com --preferred-challenges http-01 -m your-email@gmail.com --agree-tos --non-interactive

# Restart nginx
sudo systemctl start nginx

# Auto-renew SSL (already configured by certbot)
sudo certbot renew --dry-run
```

---

## STEP 7: SETUP VERCEL FRONTEND

### 7.1 Install Vercel CLI
```bash
npm i -g vercel
```

### 7.2 Deploy Frontend
```bash
cd /Users/mymac/Project/parkflow/frontend

# Login to Vercel
vercel login

# Deploy
vercel --prod

# Or use GitHub integration
# Connect repo: https://vercel.com/new
```

### 7.3 Configure Custom Domain
```
1. Go to https://vercel.com/dashboard
2. Select parkflow-frontend project
3. Go to Settings → Domains
4. Add domain: parkflow.wahyupurnamaa.com
5. Click "Add"
6. Vercel will show DNS records to add
```

### 7.4 Add DNS Records (Cloudflare/Namecheap)
```
Type: A
Name: parkflow
Value: 76.76.21.21
TTL: Auto

Type: CAA
Name: parkflow
Value: 0 issue "letsencrypt.org"
```

---

## STEP 8: GITHUB ACTIONS CI/CD (AUTOMATIC DEPLOY)

### 8.1 Create GitHub Secrets
```
1. Go to https://github.com/wahyupurnamaaa/parkflow/settings/secrets/actions
2. Add these secrets:

AWS_ACCESS_KEY_ID      → Your AWS Access Key
AWS_SECRET_ACCESS_KEY  → Your AWS Secret Key
AWS_EC2_HOST          → Your EC2 Public IP
AWS_RDS_HOST          → Your RDS Endpoint
AWS_REDIS_HOST        → Your ElastiCache Endpoint
RDS_PASSWORD          → Your RDS Password
APP_KEY               → Your Laravel APP_KEY
DOMAIN               → parkflow.wahyupurnamaa.com
```

### 8.2 Create Production Deploy Workflow
```bash
mkdir -p .github/workflows
```

Create `.github/workflows/deploy-production.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]
  workflow_dispatch:

jobs:
  # ============ FRONTEND (Vercel) ============
  deploy-frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: 'npm'
          cache-dependency-path: frontend/package-lock.json

      - name: Install Vercel
        run: npm install -g vercel

      - name: Pull Vercel Project Settings
        run: cd frontend && vercel pull yes --environment=production --token=${{ secrets.VERCEL_TOKEN }}

      - name: Build for Production
        run: cd frontend && vercel build --prod --token=${{ secrets.VERCEL_TOKEN }}

      - name: Deploy to Vercel
        run: cd frontend && vercel deploy --prebuilt --prod --token=${{ secrets.VERCEL_TOKEN }}

  # ============ BACKEND (SSH to EC2) ============
  deploy-backend:
    runs-on: ubuntu-latest
    needs: deploy-frontend
    steps:
      - uses: actions/checkout@v4

      - name: Setup SSH Key
        uses: webfactory/ssh-agent@v0.9.0
        with:
          ssh-private-key: ${{ secrets.SSH_PRIVATE_KEY }}

      - name: Deploy to EC2
        run: |
          # Add server to known hosts
          ssh-keyscan -H ${{ secrets.AWS_EC2_HOST }} >> ~/.ssh/known_hosts

          # Deploy via SSH
          ssh -o StrictHostKeyChecking=no ubuntu@${{ secrets.AWS_EC2_HOST }} << 'REMOTE_COMMANDS'
            cd /var/www/parkflow
            
            # Pull latest code
            git pull origin main
            
            # Navigate to backend
            cd backend
            
            # Update environment variables
            sed -i 's|DB_HOST=.*|DB_HOST=${{ secrets.AWS_RDS_HOST }}|' .env
            sed -i 's|REDIS_HOST=.*|REDIS_HOST=${{ secrets.AWS_REDIS_HOST }}|' .env
            
            # Rebuild containers
            docker compose down
            docker compose up -d --build
            
            # Run migrations
            docker compose exec backend php artisan migrate --force
            
            echo "✅ Backend deployed successfully!"
          REMOTE_COMMANDS

  # ============ DATABASE MIGRATION ============
  migrate-database:
    runs-on: ubuntu-latest
    needs: deploy-backend
    steps:
      - uses: actions/checkout@v4

      - name: Run Database Migration
        run: |
          ssh -o StrictHostKeyChecking=no -i ${{ secrets.SSH_KEY_PATH }} ubuntu@${{ secrets.AWS_EC2_HOST }} \
            "cd /var/www/parkflow/backend && docker compose exec backend php artisan migrate --force"

  # ============ SSL RENEWAL CHECK ============
  ssl-check:
    runs-on: ubuntu-latest
    needs: deploy-backend
    steps:
      - name: Check SSL Expiry
        run: |
          CERT=$(echo | openssl s_client -connect ${{ secrets.AWS_EC2_HOST }}:443 -servername ${{ secrets.DOMAIN }} 2>/dev/null | openssl x509 -noout -dates 2>/dev/null)
          echo "SSL Certificate Status:"
          echo "$CERT"
```

---

## STEP 9: DNS SETUP

### 9.1 Configure DNS Records
Add these records to your DNS provider (Cloudflare recommended):

```
# Main domain - Frontend (Vercel)
Type: CNAME
Name: parkflow
Value: cname.vercel-dns.com
Proxy: Yes (orange cloud)

# API subdomain - Backend (EC2)
Type: A
Name: api-parkflow
Value: YOUR_EC2_IP_ADDRESS
Proxy: No (grey cloud) - SSL needs direct IP

# Optional: Redirect parkflow.wahyupurnamaa.com
Type: CNAME
Name: parkflow
Value: vercel-frontend URL
Proxy: Yes
```

---

## STEP 10: VERIFICATION & TESTING

### 10.1 Test Backend API
```bash
# Test health endpoint
curl https://YOUR_EC2_IP:8443/api/health

# Expected response:
{"status":"ok","time":"2024-xx-xxTxx:xx:xxZ"}
```

### 10.2 Test Frontend
```
1. Buka browser
2. Kunjungi: https://parkflow.wahyupurnamaa.com
3. Should load ParkFlow dashboard
```

### 10.3 Test Login
```
URL: https://parkflow.wahyupurnamaa.com/login
Email: admin@parkflow.com
Password: ParkFlow123!
```

---

## 📊 COST SUMMARY (FREE TIER)

```
┌─────────────────────────────────────────────────────────────┐
│ YEAR 1 (12 MONTHS FREE TIER)                                │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│ AWS EC2 t3.micro        → FREE (750hr/month)               │
│ AWS RDS PostgreSQL       → FREE (db.t3.micro)               │
│ AWS ElastiCache Redis    → FREE (cache.t3.micro)            │
│ AWS S3                  → FREE (5GB)                       │
│ AWS Data Transfer        → FREE (within limits)             │
│                                                               │
│ Vercel                   → FREE (unlimited)                 │
│ Domain (wahyupurnamaa)  → ~$10-12/year ≈ $1/month        │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│ YEAR 1 TOTAL: ~$12-15 (hanya domain) ✅✅✅               │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│ YEAR 2+ (AFTER FREE TIER)                                    │
├─────────────────────────────────────────────────────────────┤
│ AWS EC2 t3.micro        → ~$10-12/month                    │
│ AWS RDS db.t3.micro     → ~$12-15/month                    │
│ AWS ElastiCache         → ~$5-8/month                      │
│ Vercel                   → FREE                             │
│ Domain                   → ~$1/month                        │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│ YEAR 2+ MONTHLY: ~$28-36/month                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🚨 IMPORTANT NOTES

### Free Tier Limits
```
AWS Free Tier Period: 12 months from account creation
After 12 months:
- EC2: $10-12/month if exceed 750hrs
- RDS: $12-15/month if exceed 750hrs

Vercel Free Tier: Forever (no time limit)
Firebase Spark: Forever (no time limit)
```

### Monitoring
```
1. Set AWS Billing Alerts
2. Monitor AWS Cost Explorer
3. Set CloudWatch alarms
4. Check free tier usage monthly
```

### Backup Strategy
```
1. RDS Automated Backups: Enable
2. Take manual snapshots monthly
3. Store critical data in S3
4. Git repository for code
```

---

## 🎯 QUICK START CHECKLIST

```
☐ Create AWS Account
☐ Create RDS PostgreSQL (free tier)
☐ Create ElastiCache Redis (free tier)
☐ Launch EC2 Instance (free tier)
☐ Setup DNS records
☐ Deploy backend to EC2
☐ Setup SSL certificate
☐ Deploy frontend to Vercel
☐ Connect GitHub Actions
☐ Test everything
```

---

## 💬 NEED HELP?

If stuck at any step, check:
1. AWS Console → CloudFormation (for stack status)
2. EC2 → Instance Status (for server status)
3. RDS → Connectivity (for database status)
4. CloudWatch Logs (for application logs)

Or create an issue at: https://github.com/wahyupurnamaaa/parkflow/issues
