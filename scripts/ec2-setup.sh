#!/bin/bash
# ===========================================
# PARKFLOW EC2 AUTOMATIC SETUP SCRIPT
# Run this on a fresh Ubuntu 24.04 EC2 instance
# Usage: bash ec2-setup.sh
# ===========================================

set -e

echo "🚀 ParkFlow EC2 Auto Setup"
echo "============================"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    echo -e "${RED}❌ Please DO NOT run as root. Run as ubuntu user.${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Running as ubuntu user${NC}"

# ===========================================
# STEP 1: Update System
# ===========================================
echo -e "\n${YELLOW}📦 Step 1: Updating system packages...${NC}"
sudo apt update -y
sudo apt upgrade -y
echo -e "${GREEN}✅ System updated${NC}"

# ===========================================
# STEP 2: Install Docker
# ===========================================
echo -e "\n${YELLOW}🐳 Step 2: Installing Docker...${NC}"

if command -v docker &> /dev/null; then
    echo "Docker already installed, skipping..."
else
    curl -fsSL https://get.docker.com | sh
    sudo usermod -aG docker ubuntu
    sudo systemctl enable docker
    sudo systemctl start docker
fi

echo -e "${GREEN}✅ Docker installed$(docker --version)${NC}"

# ===========================================
# STEP 3: Install Docker Compose Plugin
# ===========================================
echo -e "\n${YELLOW}🔧 Step 3: Checking Docker Compose...${NC}"
docker compose version
echo -e "${GREEN}✅ Docker Compose available${NC}"

# ===========================================
# STEP 4: Install Nginx
# ===========================================
echo -e "\n${YELLOW}🌐 Step 4: Installing Nginx...${NC}"
sudo apt install -y nginx
sudo systemctl enable nginx
echo -e "${GREEN}✅ Nginx installed${NC}"

# ===========================================
# STEP 5: Install Certbot (SSL)
# ===========================================
echo -e "\n${YELLOW}🔒 Step 5: Installing Certbot...${NC}"
sudo apt install -y certbot python3-certbot-nginx
echo -e "${GREEN}✅ Certbot installed${NC}"

# ===========================================
# STEP 6: Setup Firewall
# ===========================================
echo -e "\n${YELLOW}🔥 Step 6: Configuring UFW Firewall...${NC}"
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw --force enable
sudo ufw status
echo -e "${GREEN}✅ Firewall configured${NC}"

# ===========================================
# STEP 7: Create Directory Structure
# ===========================================
echo -e "\n${YELLOW}📁 Step 7: Creating directory structure...${NC}"
sudo mkdir -p /var/www/parkflow
sudo mkdir -p /var/www/certbot
sudo chown -R ubuntu:ubuntu /var/www/parkflow
sudo chown -R ubuntu:ubuntu /var/www/certbot
echo -e "${GREEN}✅ Directories created${NC}"

# ===========================================
# STEP 8: Clone Repository
# ===========================================
echo -e "\n${YELLOW}📥 Step 8: Cloning ParkFlow repository...${NC}"
cd /var/www/parkflow

if [ -d ".git" ]; then
    echo "Repository already exists, pulling latest..."
    git pull origin main
else
    git clone https://github.com/wahyupurnamaaa/parkflow.git .
fi

echo -e "${GREEN}✅ Repository cloned${NC}"

# ===========================================
# STEP 9: Setup Nginx Production Config
# ===========================================
echo -e "\n${YELLOW}🌐 Step 9: Configuring Nginx...${NC}"

# First, create a temporary HTTP-only config for SSL certificate generation
sudo tee /etc/nginx/sites-available/parkflow > /dev/null << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name api-parkflow.wahyupurnamaa.com;

    # ACME challenge for Let's Encrypt
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    # Temporary: proxy to backend (before SSL)
    location / {
        proxy_pass http://127.0.0.1:8443;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
EOF

sudo ln -sf /etc/nginx/sites-available/parkflow /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
echo -e "${GREEN}✅ Nginx configured (HTTP mode - SSL setup later)${NC}"

# ===========================================
# STEP 10: Create Backend .env
# ===========================================
echo -e "\n${YELLOW}⚙️ Step 10: Creating backend .env...${NC}"
cd /var/www/parkflow/backend

if [ ! -f .env ]; then
    cat > .env << 'ENVEOF'
APP_NAME=ParkFlow
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api-parkflow.wahyupurnamaa.com
APP_TRUST_PROXIES=*

DB_CONNECTION=pgsql
DB_HOST=REPLACE_WITH_RDS_ENDPOINT
DB_PORT=5432
DB_DATABASE=parkflow
DB_USERNAME=parkflow
DB_PASSWORD=REPLACE_WITH_RDS_PASSWORD

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis

# Redis is handled by Docker container (see docker-compose.prod.yml)
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_CLIENT=phpredis

LOG_CHANNEL=stderr
LOG_LEVEL=warning
DEMO_SEED=false
FORCE_HTTPS=true
ENVEOF

    echo -e "${YELLOW}⚠️ IMPORTANT: Edit /var/www/parkflow/backend/.env${NC}"
    echo -e "${YELLOW}   Replace DB_HOST with your RDS endpoint${NC}"
    echo -e "${YELLOW}   Replace DB_PASSWORD with your RDS password${NC}"
else
    echo ".env already exists, skipping..."
fi

echo -e "${GREEN}✅ Backend .env created${NC}"

# ===========================================
# STEP 11: Setup SSL Auto-Renewal
# ===========================================
echo -e "\n${YELLOW}🔄 Step 11: Setting up SSL auto-renewal cron...${NC}"

sudo tee /etc/cron.d/certbot-renew > /dev/null << 'EOF'
# Renew SSL certificates twice daily
0 0,12 * * * root sleep $((RANDOM \% 3600)) && certbot renew --quiet --deploy-hook "systemctl reload nginx"
EOF

echo -e "${GREEN}✅ SSL auto-renewal configured${NC}"

# ===========================================
# STEP 12: Create Deploy Helper Script
# ===========================================
echo -e "\n${YELLOW}📝 Step 12: Creating deploy helper script...${NC}"

cat > /var/www/parkflow/deploy.sh << 'DEPLOYEOF'
#!/bin/bash
set -e
echo "🚀 Deploying ParkFlow Backend..."

cd /var/www/parkflow

# Pull latest
git fetch origin main
git reset --hard origin/main

# Build and deploy
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d

echo "⏳ Waiting for services..."
sleep 15

# Run migrations
docker compose -f docker-compose.prod.yml exec -T backend php artisan migrate --force

# Cache
docker compose -f docker-compose.prod.yml exec -T backend php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T backend php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T backend php artisan view:cache

echo "✅ Deployed! Status:"
docker compose -f docker-compose.prod.yml ps
DEPLOYEOF

chmod +x /var/www/parkflow/deploy.sh

echo -e "${GREEN}✅ Deploy helper created${NC}"

# ===========================================
# COMPLETION
# ===========================================
echo ""
echo "=========================================="
echo -e "${GREEN}✅ ParkFlow EC2 Setup Complete!${NC}"
echo "=========================================="
echo ""
echo -e "${YELLOW}NEXT STEPS:${NC}"
echo ""
echo "1. Edit backend .env with your RDS credentials:"
echo "   nano /var/www/parkflow/backend/.env"
echo ""
echo "2. Generate APP_KEY:"
echo "   cd /var/www/parkflow"
echo "   docker compose -f docker-compose.prod.yml run --rm backend php artisan key:generate --show"
echo "   # Copy the key and paste it in .env"
echo ""
echo "3. Start the backend:"
echo "   cd /var/www/parkflow"
echo "   docker compose -f docker-compose.prod.yml up -d --build"
echo ""
echo "4. Run migrations:"
echo "   docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force"
echo ""
echo "5. Get SSL certificate (after DNS is pointed):"
echo "   sudo certbot --nginx -d api-parkflow.wahyupurnamaa.com"
echo ""
echo "6. Copy production Nginx config:"
echo "   sudo cp /var/www/parkflow/infrastructure/nginx-production.conf /etc/nginx/sites-available/parkflow"
echo "   sudo nginx -t && sudo systemctl reload nginx"
echo ""
echo "=========================================="
