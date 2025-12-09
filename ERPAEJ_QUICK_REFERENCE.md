# ERPAEJ Integration - Quick Reference

## 🚀 Quick Setup (5 Minutes)

### 1. Install REST API Module (2 min)

```
Administration → Modules → Browse Online → Search "REST" → Install → Enable
```

### 2. Generate API Token (1 min)

```
Administration → Modules → REST API → Configure
↓
Authentication Settings → Enable "Bearer Authentication"
↓
Generate Token → Select API User → Copy Token
```

### 3. Provide Token to ERPAEJ Team (1 min)

Give them:
```
HumHub URL: https://your-humhub-domain.com
API URL: https://your-humhub-domain.com/api/v1
API Token: eyJ0eXAiOiJKV1QiLCJhbGc...
```

### 4. Test Connection (1 min)

```bash
curl -X GET "https://your-humhub.com/api/v1/auth/current" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

✅ **Should return user info** = Setup complete!

---

## 🔑 API Token Location

After generating, token is found at:
```
Administration → Modules → REST API → Configure → Bearer Tokens
```

---

## 📋 Essential Commands

### Test API Availability
```bash
curl -X GET "https://your-humhub.com/api/v1/auth/current" \
  -H "Authorization: Bearer TOKEN"
```

### Test User Creation
```bash
curl -X POST "https://your-humhub.com/api/v1/user" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","username":"Test User","status":1}'
```

### Check Created Users
```
Administration → Users → View all users
```

---

## ⚠️ Common Issues

### Issue: 401 Unauthorized
**Fix**: Regenerate API token in HumHub

### Issue: 403 Forbidden
**Fix**: Ensure API user has Administrator role

### Issue: 422 Validation Error
**Fix**: Check email is unique and username provided

### Issue: Module not found
**Fix**: Install REST API module from Marketplace

---

## 🔒 Security Checklist

- [ ] HTTPS enabled (SSL certificate installed)
- [ ] API user is Administrator
- [ ] Token saved securely (not in public code)
- [ ] CORS configured (if needed)
- [ ] Logs enabled for monitoring

---

## 📊 Monitoring

### Check Synced Users
```
Administration → Users
```

### View API Logs
```
Administration → Information → Logs
```

### Check Failed Syncs
Ask ERPAEJ team to run:
```bash
php artisan tinker
User::where('humhub_sync_status', 'failed')->get();
```

---

## 🔄 Token Rotation (Every 90 Days)

1. Generate new token in HumHub
2. Provide new token to ERPAEJ team
3. They update their `.env` file
4. Test with single user sync

---

## 📞 Need Help?

- **HumHub Docs**: https://docs.humhub.org/
- **REST API Docs**: https://marketplace.humhub.com/module/rest/docs
- **Full Setup Guide**: See `ERPAEJ_INTEGRATION_SETUP.md`

---

## 📝 Information to Share with ERPAEJ Team

```
HumHub URL: https://____________________
API Base URL: https://____________________/api/v1
API Token: ____________________
API User Email: ____________________
```

---

**Last Updated**: December 9, 2025
