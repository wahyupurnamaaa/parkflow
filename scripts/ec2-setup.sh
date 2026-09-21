#!/bin/bash
# ===========================================
# PARKFLOW EC2 AUTOMATIC SETUP SCRIPT
# Run this once on fresh EC2 instance
# ===========================================

set -e

echo "🚀 ParkFlow EC2 Auto Setup"
echo "============================"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

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

# Install Docker
curl -fsSL https://get.docker.com | sh

# Add ubuntu user to docker group
sudo usermod -aG docker ubuntu

# Enable Docker
sudo systemctl enable docker
sudo systemctl start docker

echo -e "${GREEN}✅ Docker installed${NC}"

# ===========================================
# STEP 3: Install Docker Compose
# ===========================================
echo -e "\n${YELLOW}🔧 Step 3: Installing Docker Compose...${NC}"

# Get latest version
COMPOSE_VERSION=$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep tag_name | cut -d '"' -f 4)

# Download and install
sudo curl -L "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Verify
docker compose version

echo -e "${GREEN}✅ Docker Compose installed${NC}"

# ===========================================
# STEP 4: Install Nginx
# ===========================================
echo -e "\n${YELLOW}🌐 Step 4: Installing Nginx...${NC}"
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
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
sudo ufw allow 8443/tcp
sudo ufw --force enable
sudo ufw status
echo -e "${GREEN}✅ Firewall configured${NC}"

# ===========================================
# STEP 7: Create Directory Structure
# ===========================================
echo -e "\n${YELLOW}📁 Step 7: Creating directory structure...${NC}"
sudo mkdir -p /var/www/parkflow
sudo mkdir -p /var/www/parkflow-backend
sudo chown -R ubuntu:ubuntu /var/www/parkflow
sudo chown -R ubuntu:ubuntu /var/www/parkflow-backend
echo -e "${GREEN}✅ Directories created${NC}"

# ===========================================
# STEP 8: Install Git
# ===========================================
echo -e "\n${YELLOW}📚 Step 8: Installing Git...${NC}"
sudo apt install -y git
git --version
echo -e "${GREEN}✅ Git installed${NC}"

# ===========================================
# STEP 9: Install PHP & Composer
# ===========================================
echo -e "\n${YELLOW}🐘 Step 9: Installing PHP & Composer...${NC}"
sudo apt install -y php8.4-cli php8.4-fpm php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl php8.4-redis

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
composer --version
echo -e "${GREEN}✅ PHP & Composer installed${NC}"

# ===========================================
# STEP 10: Clone ParkFlow Repository
# ===========================================
echo -e "\n${YELLOW}📥 Step 10: Cloning ParkFlow repository...${NC}"
cd /var/www/parkflow

# Check if git repo exists
if [ -d ".git" ]; then
    echo "Repository already exists, pulling latest..."
    git pull origin main
else
    echo "Cloning repository..."
    git clone https://github.com/wahyupurnamaaa/parkflow.git .
fi

echo -e "${GREEN}✅ Repository cloned${NC}"

# ===========================================
# STEP 11: Setup Backend
# ===========================================
echo -e "\n${YELLOW}⚙️ Step 11: Setting up Backend...${NC}"
cd /var/www/parkflow/backend

# Create .env file
if [ ! -f .env ]; then
    cat > .env << 'EOF'
APP_NAME=ParkFlow
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://parkflow.wahyupurnamaa.com

DB_CONNECTION=pgsql
DB_HOST=YOUR_RDS_HOST
DB_PORT=5432
DB_DATABASE=parkflow
DB_USERNAME=parkflow
DB_PASSWORD=YOUR_DB_PASSWORD

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis

REDIS_HOST=YOUR_REDIS_HOST
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_CLIENT=phpredis

LOG_CHANNEL=stderr
DEMO_SEED=false
EOF
    echo -e "${YELLOW}⚠️ Please edit /var/www/parkflow/backend/.env with your AWS credentials${NC}"
fi

# Install dependencies
composer install --no-dev --optimize-autoloader
echo -e "${GREEN}✅ Backend dependencies installed${NC}"

# ===========================================
# STEP 12: Create Nginx Config
# ===========================================
echo -e "\n${YELLOW}🌐 Step 12: Creating Nginx configuration...${NC}"

sudo tee /etc/nginx/sites-available/parkflow > /dev/null << 'EOF'
# HTTP to HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name parkflow.wahyupurnamaa.com api-parkflow.wahyupurnamaa.com;

    # ACME challenge
    location /.well-known/acme-challenge/ {
        root /var/www/parkflow/certbot;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

# HTTPS Backend API
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api-parkflow.wahyupurnamaa.com;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/parkflow.wahyupurnamaa.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/parkflow.wahyupurnamaa.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # API Proxy
    location / {
        proxy_pass http://127.0.0.1:8443;
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

        # Buffering
        proxy_buffering off;
        proxy_request_buffering off;
    }

    # Health check
    location /health {
        access_log off;
        return 200 "OK\n";
        add_header Content-Type text/plain;
    }
}
EOF

# Enable site
sudo ln -sf /etc/nginx/sites-available/parkflow /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
echo -e "${GREEN}✅ Nginx configured${NC}"

# ===========================================
# STEP 13: Create Certbot Directory
# ===========================================
echo -e "\n${YELLOW}🔒 Step 13: Creating SSL directory...${NC}"
sudo mkdir -p /var/www/parkflow/certbot
sudo chown -R ubuntu:ubuntu /var/www/parkflow/certbot
echo -e "${GREEN}✅ SSL directory created${NC}"

# ===========================================
# STEP 14: Setup Docker Backend
# ===========================================
echo -e "\n${YELLOW}🐳 Step 14: Creating Docker backend service...${NC}"

# Create systemd service for backend
sudo tee /etc/systemd/system/parkflow-backend.service > /dev/null << 'EOF'
[Unit]
Description=ParkFlow Backend Service
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=/var/www/parkflow/backend
ExecStart=/usr/local/bin/docker-compose up -d
ExecStop=/usr/local/bin/docker-compose down
User=ubuntu
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd
sudo systemctl daemon-reload
echo -e "${GREEN}✅ Backend service created${NC}"

# ===========================================
# STEP 15: Setup Automatic SSL Renewal
# ===========================================
echo -e "\n${YELLOW}🔄 Step 15: Setting up SSL auto-renewal...${NC}"

# Create renewal script
sudo tee /etc/cron.d/certbot-renew > /dev/null << 'EOF'
# Renew SSL certificates twice daily
0 0,12 * * * root sleep $((RANDOM \% 3600)) && certbot renew --quiet --deploy-hook "systemctl reload nginx"
EOF

echo -e "${GREEN}✅ SSL auto-renewal configured${NC}"

# ===========================================
# STEP 16: Setup Log Rotation
# ===========================================
echo -e "\n${YELLOW}📝 Step 16: Setting up log rotation...${NC}"
sudo tee /etc/logrotate.d/parkflow > /dev/null << 'EOF'
/var/www/parkflow/backend/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 ubuntu ubuntu
    sharedscripts
    postrotate
        docker compose -f /var/www/parkflow/backend/docker-compose.yml restart > /dev/null 2>&1 || true
    endscript
}
EOF
echo -e "${GREEN}✅ Log rotation configured${NC}"

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
echo "1. Edit backend environment file:"
echo "   nano /var/www/parkflow/backend/.env"
echo ""
echo "2. Update these values:"
echo "   - DB_HOST: Your RDS endpoint"
echo "   - DB_PASSWORD: Your RDS password"
echo "   - REDIS_HOST: Your ElastiCache endpoint"
echo ""
echo "3. Generate APP_KEY:"
echo "   cd /var/www/parkflow/backend"
echo "   php artisan key:generate"
echo ""
echo "4. Get SSL certificate:"
echo "   sudo certbot --nginx -d parkflow.wahyupurnamaa.com"
echo ""
echo "5. Start backend:"
echo "   cd /var/www/parkflow/backend"
echo "   docker compose up -d"
echo ""
echo "6. Run migrations:"
echo "   docker compose exec backend php artisan migrate"
echo ""
echo "=========================================="
