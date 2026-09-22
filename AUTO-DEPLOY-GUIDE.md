# ===========================================
# AUTO-DEPLOY TO HOSTINGER (FREE!)
# ParkFlow GitHub → Hostinger Auto-Deploy
# ===========================================

## 🏗️ How It Works

```
┌─────────────────────────────────────────────────────────────┐
│                     AUTO-DEPLOY FLOW                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   💻 Developer                                               │
│       │                                                     │
│       │ git push origin main                                │
│       ▼                                                     │
│   ┌─────────────────────────────────────────────────┐     │
│   │              GITHUB ACTIONS                       │     │
│   │  ┌─────────────┐  ┌─────────────┐              │     │
│   │  │   Tests     │→│   Build     │→│   Deploy    │     │
│   │  │  ✅/❌      │  │  Frontend   │  │  FTP→Hostinger│     │
│   │  └─────────────┘  └─────────────┘  └─────────────┘     │
│   └─────────────────────────────────────────────────┘     │
│                        │                                    │
│                        │ FTP Upload                         │
│                        ▼                                    │
│   ┌─────────────────────────────────────────────────┐     │
│   │           HOSTINGER SERVER                       │     │
│   │                                                   │     │
│   │   public_html/                                  │     │
│   │   └── parkflow/ (or root)                       │     │
│   │       ├── backend/ (Laravel)                    │     │
│   │       ├── .next/ (Next.js build)                │     │
│   │       └── public/ (Static assets)               │     │
│   │                                                   │     │
│   └─────────────────────────────────────────────────┘     │
│                        │                                    │
│                        │ ✅ Auto-live!                       │
│                        ▼                                    │
│   👤 User visits: https://wahyupurnamaa.com               │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

## 📋 Prerequisites

Before setting up auto-deploy, make sure you have:

- ✅ GitHub account with access to `wahyupurnamaaa/parkflow`
- ✅ Hostinger hosting (already have!)
- ✅ FTP credentials from Hostinger hPanel

## 🚀 Setup Steps

### STEP 1: Get FTP Credentials from Hostinger

```
1. Login to hPanel: https://hpanel.hostinger.com

2. Navigate: Hosting → wahyupurnamaa.com → Advanced → FTP

3. Create FTP Account:
   - FTP Username: wahyu
   - Domain: wahyupurnamaa.com
   - Directory: public_html/parkflow (or leave empty for root)
   - Password: [create strong password]
   
4. Note these values:
   - FTP Host: files.wahyupurnamaa.com
   - FTP Username: wahyu@wahyupurnamaa.com
   - FTP Password: [the password you created]
```

### STEP 2: Add GitHub Secrets

```
1. Go to: https://github.com/wahyupurnamaaa/parkflow/settings/secrets/actions

2. Click "New repository secret" for each:

┌────────────────────────────────────────────────────────┐
│ SECRET #1                                              │
├────────────────────────────────────────────────────────┤
│ Name: FTP_HOST                                         │
│ Secret: files.wahyupurnamaa.com                       │
│ → Click "Add secret"                                  │
└────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│ SECRET #2                                              │
├────────────────────────────────────────────────────────┤
│ Name: FTP_USERNAME                                     │
│ Secret: wahyu@wahyupurnamaa.com                       │
│ → Click "Add secret"                                   │
└────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│ SECRET #3                                              │
├────────────────────────────────────────────────────────┤
│ Name: FTP_PASSWORD                                     │
│ Secret: [your FTP password]                           │
│ → Click "Add secret"                                  │
└────────────────────────────────────────────────────────┘
```

### STEP 3: Push Updated Code

The deployment files have been created. Push to GitHub:

```bash
cd /Users/mymac/Project/parkflow
git add -A
git commit -m "feat: add auto-deploy to Hostinger

- Add GitHub Actions workflow for Hostinger deployment
- Add quick deploy workflow for hotfixes
- Add local deploy script

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
git push origin main
```

### STEP 4: Watch the Magic Happen

```
1. Go to: https://github.com/wahyupurnamaaa/parkflow/actions

2. You should see a new workflow running:
   - Name: "Deploy to Hostinger (Auto)"
   - Status: 🟡 Running (yellow)
   
3. Click on the workflow to see progress:
   - ✅ quality job (tests)
   - ⏳ deploy job (FTP upload)
   - ⏳ verify job (health check)
   
4. When complete:
   - ✅ All green = Success!
   - 🌐 Website auto-updated!
```

---

## 📁 Files Created

```
parkflow/
├── .github/workflows/
│   ├── deploy-hostinger.yml   ← Main auto-deploy workflow
│   └── quick-deploy.yml       ← Fast deploy (skip tests)
├── scripts/
│   └── deploy.sh              ← Local deploy script
└── AUTO-DEPLOY-GUIDE.md     ← This file
```

---

## 🎮 How to Trigger Deploy

### Option 1: Push to GitHub (Recommended)

```bash
# Make changes to code
git add .
git commit -m "your changes"
git push origin main

# → Auto-deploy triggers!
```

### Option 2: Manual Trigger

```
1. Go to: https://github.com/wahyupurnamaaa/parkflow/actions

2. Click "Deploy to Hostinger (Auto)" workflow

3. Click "Run workflow" dropdown

4. Click green "Run workflow" button

5. Deployment starts!
```

### Option 3: Quick Deploy (Hotfix)

```
1. Go to: https://github.com/wahyupurnamaaa/parkflow/actions

2. Click "Quick Deploy (Skip Tests)" workflow

3. Click "Run workflow"

4. Fast deployment without tests!
```

### Option 4: Local Deploy Script

```bash
# Make changes
cd /Users/mymac/Project/parkflow

# Deploy directly (no GitHub)
chmod +x scripts/deploy.sh
./scripts/deploy.sh --ftp --build

# Or skip build if already built
./scripts/deploy.sh --ftp
```

---

## 🔧 Workflow Details

### `deploy-hostinger.yml` - Main Workflow

**Triggers:**
- Push to `main` branch
- Manual trigger (workflow_dispatch)

**Jobs:**

1. **quality** (Tests)
   - PHP setup + Laravel tests
   - Node.js setup + TypeScript check
   - Frontend build

2. **deploy** (FTP Upload)
   - Create deployment package
   - Upload to Hostinger via FTP
   - Uses `SamKirkland/FTP-Deploy-Action@v4.3`

3. **verify** (Health Check)
   - Test website accessibility
   - Report deployment status

**Total Time:** ~5-8 minutes

### `quick-deploy.yml` - Fast Workflow

**Triggers:**
- Manual trigger only (workflow_dispatch)

**Jobs:**
- Build only (no tests!)
- Deploy immediately

**Total Time:** ~2-3 minutes

**Use Case:** Hotfixes when you need to deploy NOW

---

## ⚠️ Troubleshooting

### Deployment Failed?

```
1. Check GitHub Actions logs:
   https://github.com/wahyupurnamaaa/parkflow/actions

2. Common issues:

   ❌ FTP Credentials Wrong
   → Re-verify FTP credentials in hPanel
   → Update GitHub secrets

   ❌ Build Failed
   → Check Node.js/PHP version
   → Run locally first: npm ci && npm run build

   ❌ FTP Timeout
   → Large files may timeout
   → Try quick deploy with smaller package

   ❌ Website Shows Old Version
   → Clear browser cache
   → Check FTP upload was complete
   → Verify files in Hostinger File Manager
```

### Verify FTP Connection

```bash
# Test FTP manually
lftp -u wahyu@wahyupurnamaa.com,YOUR_PASSWORD files.wahyupurnamaa.com

# Inside lftp:
lftp :~> ls
lftp :~> bye
```

### Check Files in Hostinger

```
1. Login to hPanel
2. Go to: Hosting → Advanced → File Manager
3. Navigate to public_html
4. Verify files are uploaded
```

---

## 📊 Cost

```
┌────────────────────────────────────────────────────────┐
│                    COST: $0 FREE!                      │
├────────────────────────────────────────────────────────┤
│                                                        │
│ GitHub Actions      → FREE (public repo, 2000 min/mo)  │
│ Hostinger Hosting   → Already paying                   │
│ FTP Deploy          → FREE                            │
│ SSL Certificate     → FREE (Let's Encrypt)             │
│                                                        │
│ TOTAL MONTHLY COST: $0 ✅✅✅                          │
│                                                        │
└────────────────────────────────────────────────────────┘
```

---

## 🎯 Deployment Checklist

```
□ FTP credentials created in hPanel
□ GitHub secrets added:
  □ FTP_HOST
  □ FTP_USERNAME
  □ FTP_PASSWORD
□ Code pushed to GitHub
□ First auto-deploy successful
□ Website updated automatically
□ ✓ All green checkmarks in GitHub Actions
```

---

## 💡 Pro Tips

### 1. Use Branches for Testing

```bash
# Create feature branch
git checkout -b feature/new-feature

# Make changes
git add .
git commit -m "new feature"
git push origin feature/new-feature

# When ready, merge to main
git checkout main
git merge feature/new-feature
git push origin main

# → Auto-deploy triggers after merge
```

### 2. Preview Before Deploy

```bash
# Run locally first
cd frontend
npm run dev

# Open: http://localhost:3000
# Test your changes
# When happy: git push
```

### 3. Rollback if Needed

```bash
# In Hostinger File Manager:
# 1. Go to public_html
# 2. Find the previous version
# 3. Rename folders:
#    - current/ → old/
#    - backup-date/ → current/

# Or via FTP:
# 1. Download previous version
# 2. Re-upload
```

### 4. Monitor Deployments

```
1. Star the repo for notifications
2. Enable GitHub mobile app
3. Get push notifications on phone
```

---

## 🆘 Need Help?

- **GitHub Actions:** https://docs.github.com/en/actions
- **FTP Deploy Action:** https://github.com/marketplace/actions/ftp-deploy
- **Hostinger FTP Guide:** https://www.hostinger.com/tutorials/how-to-use-ftp/

---

**🎉 Happy Auto-Deploying!**
