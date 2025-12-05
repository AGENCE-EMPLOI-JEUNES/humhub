# Two-Way SSO Quick Start Guide

## Overview
You can now navigate seamlessly between ERP (SIGA-AEJ) and HumHub in both directions with secure SSO!

## What's New? ✨

### 1. ERP → HumHub (Already working)
- Click "HUMHUB" in the ERP platform menu
- Automatically logs you into HumHub

### 2. HumHub → ERP (NEW!)
- Look for **"SIGA-AEJ"** in the HumHub top navigation bar
- It appears after: MY SPACES | DASHBOARD | PEOPLE | SPACES | **SIGA-AEJ**
- Click it to automatically log into ERP in a new tab

### 3. Logout Behavior (NEW!)
- When you log out of HumHub, you're automatically redirected to ERP
- This keeps you in the ERP ecosystem for a seamless experience

## Configuration Required

### HumHub Configuration

Make sure `erpBaseUrl` is configured in HumHub:

**Option 1: Environment Variable** (Recommended)
```bash
export ERP_BASE_URL=https://erp.agenceemploijeunes.ci
```

**Option 2: Config File**
Edit `humhub/protected/config/web.php`:
```php
return [
    'params' => [
        'erpApiUrl' => 'https://erp.agenceemploijeunes.ci/api',
        'erpBaseUrl' => 'https://erp.agenceemploijeunes.ci',  // Add this line
    ],
];
```

### ERP Configuration

Make sure HumHub URL is configured in ERP:

Edit `erpaej/config/siga.php`:
```php
'urls' => [
    'humhub' => 'https://humhub.agenceemploijeunes.ci',
]
```

## Testing

### Test HumHub → ERP

1. **Log into HumHub**
   - Go to https://humhub.agenceemploijeunes.ci

2. **Check for SIGA-AEJ Link**
   - Look in the top navigation bar
   - You should see: MY SPACES | DASHBOARD | PEOPLE | SPACES | **SIGA-AEJ**
   
3. **Click SIGA-AEJ**
   - Opens ERP in a new tab
   - Should automatically log you in
   - URL should contain `?humhub_token=` (not your email)

4. **Verify Success**
   - You should land on the ERP dashboard
   - Should be logged in as the same user

### Test Logout Redirect

1. **While logged into HumHub**
   - Click on your profile picture/username (top right)
   - Click "Logout"

2. **Verify Redirect**
   - You should be redirected to ERP (https://erp.agenceemploijeunes.ci)
   - Not to HumHub login page

### Troubleshooting

**Problem**: "SIGA-AEJ" link not showing in HumHub navigation

**Solutions**:
1. Check that `erpBaseUrl` is configured in `humhub/protected/config/web.php`
2. Make sure you're logged in (link only shows for authenticated users)
3. Clear HumHub cache:
   ```bash
   cd /path/to/humhub
   php protected/yii cache/flush-all
   ```
4. Check logs:
   ```bash
   tail -f protected/runtime/logs/app.log | grep "ERP SSO"
   ```

**Problem**: Token validation error when clicking SIGA-AEJ

**Solutions**:
1. Verify HumHub URL is correct in ERP config (`erpaej/config/siga.php`)
2. Check that both systems can reach each other
3. Verify cache is working in HumHub (tokens are stored in cache)
4. Check ERP logs:
   ```bash
   cd /path/to/erpaej
   tail -f storage/logs/laravel.log | grep "HumHub SSO"
   ```

## Security

Both directions use the same secure token-based SSO:
- ✅ 64-character random tokens
- ✅ Single-use (deleted after validation)
- ✅ 5-minute expiration
- ✅ No email in URLs
- ✅ API validation between systems

## Support

If you encounter issues:
1. Check both HumHub and ERP logs
2. Verify network connectivity between systems
3. Ensure cache is working (Redis/Memcached recommended)
4. Test manually with curl:
   ```bash
   # Test HumHub token validation endpoint
   curl -X POST https://humhub.test/api/erp/validate-token \
     -H "Content-Type: application/json" \
     -d '{"token":"test"}'
   ```

---

**Last Updated**: December 3, 2025

