# ===========================================
# PARKFLOW DEPLOYMENT README
# FREE TIER SETUP
# ===========================================

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         PARKFLOW FREE TIER                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│   ┌──────────────┐         ┌──────────────────┐                     │
│   │    USERS     │────────▶│      VERCEL       │                     │
│   │              │         │   (Frontend)      │                     │
│   │  🌐 Web      │         │   ✅ FREE        │                     │
│   │  📱 Mobile   │         │  (parkflow.xxx)  │                     │
│   └──────────────┘         └──────────────────┘                     │
│                                    │                                 │
│                                    │ API calls                       │
│                                    ▼                                 │
│   ┌──────────────────────────────────────────────────────────────────┐
│   │                        AWS EC2 (t3.micro)                         │
│   │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│   │  │   Nginx      │  │   Laravel    │  │    Queue     │          │
│   │  │   + SSL      │──▶│   Backend   │──▶│   Worker    │          │
│   │  │   (Port 443) │  │   (PHP 8.4) │  │              │          │
│   │  └──────────────┘  └──────────────┘  └──────────────┘          │
│   │                         ✅ FREE TIER                             │
│   └──────────────────────────────────────────────────────────────────┘
│                         │                    │                         │
│                         │ PostgreSQL         │ Redis                  │
│                         ▼                    ▼                         │
│   ┌──────────────────────────────────────────────────────────────────┐
│   │                      AWS SERVICES (FREE TIER)                    │
│   │  ┌──────────────────┐         ┌──────────────────┐             │
│   │  │   RDS PostgreSQL  │         │  ElastiCache     │             │
│   │  │   (db.t3.micro)  │         │  Redis           │             │
│   │  │   20GB Storage   │         │  (cache.t3.micro)│             │
│   │  │   ✅ FREE        │         │  ✅ FREE         │             │
│   │  └──────────────────┘         └──────────────────┘             │
│   └──────────────────────────────────────────────────────────────────┘
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

## 📊 FREE TIER LIMITS

### AWS Free Tier (12 Months)
| Service | Free Tier Limit | After 12 Months |
|---------|----------------|-----------------|
| EC2 t3.micro | 750 hrs/month | ~$10-12/month |
| RDS PostgreSQL | 750 hrs/month | ~$12-15/month |
| ElastiCache Redis | 750 hrs/month | ~$5-8/month |
| S3 | 5GB | ~$0.5/month |

### Vercel (Forever)
| Resource | Free Tier |
|----------|-----------|
| Projects | Unlimited |
| Bandwidth | Unlimited |
| SSL | ✅ Free |
| Custom Domain | ✅ Free |

## 🚀 QUICK START

### Option 1: Manual Setup (Step by Step)

1. **Setup AWS EC2**
   ```bash
   # SSH to EC2
   ssh -i "parkflow-key.pem" ubuntu@YOUR_EC2_IP
   
   # Run setup script
   chmod +x /var/www/parkflow/scripts/ec2-setup.sh
   ./var/www/parkflow/scripts/ec2-setup.sh
   ```

2. **Setup RDS PostgreSQL**
   - Go to AWS Console → RDS → Create Database
   - Choose: PostgreSQL 17, db.t3.micro (Free tier)
   - Copy endpoint

3. **Setup ElastiCache Redis**
   - Go to AWS Console → ElastiCache → Create Redis
   - Choose: cache.t3.micro (Free tier)
   - Copy endpoint

4. **Deploy Backend**
   ```bash
   cd /var/www/parkflow/backend
   nano .env  # Edit with AWS credentials
   docker compose -f docker-compose.prod.yml up -d
   ```

5. **Deploy Frontend**
   ```bash
   cd frontend
   vercel --prod
   ```

### Option 2: GitHub Actions (Fully Automated)

1. Add GitHub Secrets:
   ```
   VERCEL_TOKEN
   VERCEL_ORG_ID
   VERCEL_PROJECT_ID
   SSH_PRIVATE_KEY
   AWS_EC2_HOST
   AWS_RDS_HOST
   AWS_ELASTICACHE_HOST
   RDS_PASSWORD
   ```

2. Push to main branch → Auto deploy!

## 📁 Project Structure

```
parkflow/
├── .github/
│   └── workflows/
│       ├── ci.yml              # CI pipeline
│       └── deploy-production.yml # Auto deploy
├── backend/
│   ├── app/
│   ├── docker/
│   ├── .env                    # Edit for production
│   └── docker-compose.prod.yml
├── frontend/
│   ├── vercel.json            # Vercel config
│   └── src/
├── scripts/
│   └── ec2-setup.sh           # EC2 auto setup
├── DEPLOYMENT-GUIDE.md        # This guide
└── docker-compose.prod.yml
```

## 🌐 DNS Configuration

Add these records to your DNS provider:

```
# Frontend (Vercel)
parkflow.wahyupurnamaa.com    CNAME    cname.vercel-dns.com

# Backend (EC2)
api-parkflow.wahyupurnamaa.com  A     YOUR_EC2_IP

# Optional: Redirect
parkflow.wahyupurnamaa.com    A     YOUR_EC2_IP (backup)
```

## 🔒 SSL Certificate

SSL is automatically managed by Certbot:

```bash
# Get certificate
sudo certbot --nginx -d parkflow.wahyupurnamaa.com -d api-parkflow.wahyupurnamaa.com

# Auto renew (already configured)
sudo certbot renew --dry-run
```

## 📊 Monitoring

### AWS CloudWatch
- EC2: CPU, Network, Status
- RDS: Connections, Queries, Storage
- ElastiCache: Memory, CPU

### Vercel Analytics
- Page views
- Performance
- Core Web Vitals

## 💰 Cost Estimation

### Year 1 (AWS Free Tier Active)
- AWS Services: $0 ✅
- Vercel: $0 ✅
- Domain: ~$1/month
- **Total: ~$12/year**

### Year 2+ (After Free Tier)
- EC2: ~$10-12/month
- RDS: ~$12-15/month
- ElastiCache: ~$5-8/month
- Vercel: $0 ✅
- **Total: ~$27-35/month**

## 🆘 Troubleshooting

### Backend not starting?
```bash
docker compose -f docker-compose.prod.yml logs backend
```

### Database connection failed?
```bash
# Check security group allows port 5432
# Test connection:
psql -h YOUR_RDS_ENDPOINT -U parkflow -d parkflow
```

### Redis connection failed?
```bash
# Check security group allows port 6379
# Test connection:
redis-cli -h YOUR_REDIS_ENDPOINT ping
```

### SSL certificate issue?
```bash
sudo certbot certificates
sudo certbot renew --force-renewal
```

## 📞 Support

- GitHub Issues: https://github.com/wahyupurnamaaa/parkflow/issues
- AWS Documentation: https://docs.aws.amazon.com
- Vercel Docs: https://vercel.com/docs

---

**🎉 Happy Deploying!**
