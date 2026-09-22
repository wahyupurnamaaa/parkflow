#!/bin/bash
# ===========================================
# PARKFLOW DEPLOY TO HOSTINGER
# Run this locally or via SSH
# ===========================================

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo "🚀 ParkFlow Deploy to Hostinger"
echo "================================"

# ===========================================
# CONFIGURATION
# ===========================================

# FTP Credentials (CHANGE THESE!)
FTP_HOST="files.wahyupurnamaa.com"
FTP_USER="wahyu@wahyupurnamaa.com"
FTP_PASS=""
FTP_REMOTE_DIR="/public_html"

# Alternative: SSH Credentials (if available)
SSH_HOST=""
SSH_USER=""
SSH_PASS=""

# ===========================================
# FUNCTIONS
# ===========================================

usage() {
    echo "Usage: ./deploy.sh [options]"
    echo ""
    echo "Options:"
    echo "  -h, --help      Show this help"
    echo "  -f, --ftp       Deploy via FTP"
    echo "  -s, --ssh       Deploy via SSH"
    echo "  -b, --build     Build before deploy"
    echo ""
    echo "Examples:"
    echo "  ./deploy.sh --ftp --build    # Build and deploy via FTP"
    echo "  ./deploy.sh --ssh            # Deploy via SSH"
    echo ""
}

check_requirements() {
    echo -e "${YELLOW}📋 Checking requirements...${NC}"

    # Check git
    if ! command -v git &> /dev/null; then
        echo -e "${RED}❌ Git not found${NC}"
        exit 1
    fi

    # Check for lftp or ftp
    if ! command -v lftp &> /dev/null && ! command -v ftp &> /dev/null; then
        echo -e "${YELLOW}⚠️ FTP client not found. Installing lftp...${NC}"
        if command -v apt-get &> /dev/null; then
            sudo apt-get install -y lftp
        elif command -v brew &> /dev/null; then
            brew install lftp
        fi
    fi

    echo -e "${GREEN}✅ Requirements OK${NC}"
}

build_frontend() {
    echo -e "${YELLOW}🔨 Building Frontend...${NC}"

    if [ ! -d "frontend" ]; then
        echo -e "${RED}❌ frontend directory not found${NC}"
        exit 1
    fi

    cd frontend

    # Install dependencies
    npm ci

    # Build
    npm run build

    cd ..

    echo -e "${GREEN}✅ Frontend built${NC}"
}

build_backend() {
    echo -e "${YELLOW}🐘 Building Backend (Laravel)...${NC}"

    if [ ! -d "backend" ]; then
        echo -e "${RED}❌ backend directory not found${NC}"
        exit 1
    fi

    cd backend

    # Install dependencies
    composer install --no-dev --optimize-autoloader

    # Generate key if not exists
    if grep -q "APP_KEY=" .env && grep -q "APP_KEY=$" .env; then
        php artisan key:generate --force
    fi

    cd ..

    echo -e "${GREEN}✅ Backend built${NC}"
}

create_package() {
    echo -e "${YELLOW}📦 Creating deployment package...${NC}"

    # Remove old package
    rm -rf deploy-package

    # Create new package
    mkdir -p deploy-package

    # Copy backend
    echo "Copying backend..."
    cp -r backend/* deploy-package/

    # Copy frontend build
    echo "Copying frontend build..."
    cp -r frontend/.next deploy-package/ 2>/dev/null || true
    cp -r frontend/public deploy-package/ 2>/dev/null || true

    # Copy environment file
    if [ -f ".env.production" ]; then
        cp .env.production deploy-package/.env
    fi

    # Create version file
    echo "Deploy at: $(date)" > deploy-package/version.txt
    echo "Commit: $(git rev-parse HEAD)" >> deploy-package/version.txt
    echo "Branch: $(git branch --show-current)" >> deploy-package/version.txt

    # Remove unnecessary files
    echo "Cleaning up..."
    rm -rf deploy-package/.git
    rm -rf deploy-package/node_modules
    rm -rf deploy-package/vendor/.git
    rm -rf deploy-package/storage/*.key

    echo -e "${GREEN}✅ Package created${NC}"
}

deploy_ftp() {
    echo -e "${YELLOW}📤 Deploying via FTP...${NC}"

    if [ -z "$FTP_PASS" ]; then
        echo -e "${RED}❌ FTP_PASSWORD not set${NC}"
        echo "Set FTP_PASS variable at the top of this script"
        exit 1
    fi

    # Using lftp for reliable FTP
    if command -v lftp &> /dev/null; then
        lftp -c "
            set ftp:ssl-allow no
            open -u $FTP_USER,$FTP_PASS $FTP_HOST
            mirror -R --delete deploy-package/ $FTP_REMOTE_DIR/
            bye
        "
    else
        # Fallback to ftp
        ftp -p -n $FTP_HOST << EOF
            user $FTP_USER $FTP_PASS
            passive
            prompt off
            mirror -R deploy-package/ $FTP_REMOTE_DIR/
            bye
EOF
    fi

    echo -e "${GREEN}✅ Deployed via FTP${NC}"
}

deploy_ssh() {
    echo -e "${YELLOW}📤 Deploying via SSH...${NC}"

    if [ -z "$SSH_PASS" ]; then
        echo -e "${RED}❌ SSH credentials not configured${NC}"
        exit 1
    fi

    sshpass -p "$SSH_PASS" scp -r deploy-package/* $SSH_USER@$SSH_HOST:$SSH_REMOTE_DIR/

    echo -e "${GREEN}✅ Deployed via SSH${NC}"
}

cleanup() {
    echo -e "${YELLOW}🧹 Cleaning up...${NC}"
    rm -rf deploy-package
    echo -e "${GREEN}✅ Cleanup complete${NC}"
}

verify() {
    echo -e "${YELLOW}🔍 Verifying deployment...${NC}"

    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://wahyupurnamaa.com)

    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "${GREEN}✅ Website is live! (HTTP $HTTP_CODE)${NC}"
    else
        echo -e "${YELLOW}⚠️ Website returned HTTP $HTTP_CODE${NC}"
    fi
}

# ===========================================
# MAIN
# ===========================================

# Parse arguments
BUILD=false
FTP_DEPLOY=false
SSH_DEPLOY=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            usage
            exit 0
            ;;
        -f|--ftp)
            FTP_DEPLOY=true
            shift
            ;;
        -s|--ssh)
            SSH_DEPLOY=true
            shift
            ;;
        -b|--build)
            BUILD=true
            shift
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            usage
            exit 1
            ;;
    esac
done

# Default to FTP if nothing specified
if [ "$FTP_DEPLOY" = false ] && [ "$SSH_DEPLOY" = false ]; then
    FTP_DEPLOY=true
fi

# Run deployment
check_requirements

if [ "$BUILD" = true ]; then
    build_frontend
    build_backend
fi

create_package

if [ "$FTP_DEPLOY" = true ]; then
    deploy_ftp
fi

if [ "$SSH_DEPLOY" = true ]; then
    deploy_ssh
fi

verify
cleanup

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✅ DEPLOYMENT COMPLETE!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "🌐 Website: https://wahyupurnamaa.com"
echo "⏰ Deployed at: $(date)"
echo ""
