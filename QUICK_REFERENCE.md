# HumHub SSO - Quick Reference

## 🔒 Secure Token-Based SSO

### How URLs Look Now

**✅ Secure (Current)**
```
https://humhub.test/user/auth/login?erp_token=CWNr6HUg7Xi3HNlpkZNP...
```
- Email is NOT visible
- Token is single-use
- Expires in 5 minutes

**❌ Old (Insecure)**
```
https://humhub.test/user/auth/login?erp_email=admin@emploijeunes.ci
```

---

## 📋 Quick Start

### 1. Configure ERP API URL in HumHub

Edit: `humhub/protected/config/web.php`
```php
'params' => [
    'erpApiUrl' => 'https://erp.yourdomain.com/api',
]
```

Or set environment variable:
```bash
export ERP_API_URL=https://erp.yourdomain.com/api
```

### 2. Ensure Cache is Configured in ERP

Edit: `erpaej/.env`
```env
CACHE_DRIVER=redis  # or memcached
```

### 3. Test It
1. Log into ERP
2. Click HumHub module
3. Check URL - should have `erp_token=` not `erp_email=`
4. Should be logged into HumHub automatically

---

## 🔍 Troubleshooting

### "Token invalid or expired"

**Possible Causes:**
- Token older than 5 minutes
- User clicked the link twice
- Cache not working

**Fix:**
1. Generate a new link by going back to ERP menu
2. Click HumHub again (new token will be generated)

### "Authentication service unavailable"

**Possible Causes:**
- ERP server is down
- Network issue between HumHub and ERP
- Wrong API URL configured

**Fix:**
1. Verify ERP is running: `curl https://erp.yourdomain.com/api/humhub/validate-token`
2. Check HumHub config: `humhub/protected/config/web.php`
3. Check network connectivity from HumHub server to ERP

### Check Logs

**ERP Logs:**
```bash
cd /path/to/erpaej
tail -f storage/logs/laravel.log | grep "HumHub SSO"
```

**HumHub Logs:**
```bash
cd /path/to/humhub
tail -f protected/runtime/logs/app.log | grep "ERP SSO"
```

---

## 🔐 Security Features

| Feature | Status |
|---------|--------|
| Email hidden in URLs | ✅ |
| Single-use tokens | ✅ |
| Time-limited (5 min) | ✅ |
| API validation | ✅ |
| Replay protection | ✅ |

---

## 📞 Support

**Check Documentation:**
- Full guide: `humhub/ERP_SSO_INTEGRATION.md`
- Security details: `humhub/SECURITY_UPGRADE.md`

**Debug Steps:**
1. Check both ERP and HumHub logs
2. Verify user exists in HumHub with same email
3. Test API endpoint manually with curl
4. Verify cache is working in ERP

---

**Last Updated**: December 2, 2025

