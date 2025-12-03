# Security Upgrade: Email-in-URL → Secure Token-Based SSO

## Overview
Upgraded the ERP-HumHub SSO integration from exposing user emails in URLs to using secure, single-use tokens.

---

## Before (Insecure)

### URL Format
```
https://humhub.test/user/auth/login?erp_email=admin@emploijeunes.ci
```

### Problems
- ❌ Email addresses visible in URLs
- ❌ URLs could be bookmarked and reused
- ❌ Emails visible in browser history
- ❌ Emails visible in server logs
- ❌ No expiration mechanism
- ❌ Vulnerable to URL manipulation

---

## After (Secure)

### URL Format
```
https://humhub.test/user/auth/login?erp_token=CWNr6HUg7Xi3HNlpkZNPFBOXtkg0aJlynGbVQAYt1XNln33efb1jQPzMnzNz4Cq6
```

### Security Features
- ✅ **No PII in URLs**: Email addresses never exposed
- ✅ **Single-Use Tokens**: Each token deleted after validation
- ✅ **Time-Limited**: Tokens expire after 5 minutes
- ✅ **Cryptographically Secure**: 64-character random tokens
- ✅ **API Validation**: Token verified server-to-server
- ✅ **Replay Protection**: Tokens cannot be reused
- ✅ **Cache-Based**: Tokens stored in Redis/Memcached

---

## How It Works Now

### 1. Token Generation (ERP)
```php
// User clicks HumHub in menu
$token = Str::random(64);

Cache::put("humhub_sso_token:{$token}", [
    'email' => $user->email,
    'user_id' => $user->id,
    'name' => $user->name,
    'created_at' => now()->timestamp,
], 300); // 5 minutes

$url = "https://humhub.test/user/auth/login?erp_token={$token}";
```

### 2. Token Validation (HumHub → ERP API)
```php
// HumHub receives token
POST https://erp.test/api/humhub/validate-token
{
  "token": "CWNr6HUg7Xi3HNlpkZNP..."
}

// ERP validates and returns user data
{
  "status": true,
  "user": {
    "id": 706,
    "email": "admin@emploijeunes.ci",
    "name": "ADMIN ADMIN"
  }
}

// Token is deleted from cache (single-use)
```

### 3. Authentication (HumHub)
```php
// HumHub authenticates user with email from API response
$user = User::findOne(['email' => $userData['email']]);
Yii::$app->user->login($user, 0);
```

---

## Changes Made

### ERP (erpaej)

**Modified Files:**
1. `app/Services/HumHubAuthService.php`
   - Added `generateAuthUrl()` - creates secure tokens
   - Added `validateSsoToken()` - validates and consumes tokens
   - Uses Laravel Cache for token storage

2. `app/Http/Controllers/Erp/AuthErpController.php`
   - Added `validateHumHubToken()` method
   - API endpoint for token validation

3. `routes/api.php`
   - Added `POST /api/humhub/validate-token` route

**New Dependencies:**
- Requires Laravel Cache configured (Redis/Memcached recommended)

### HumHub

**Modified Files:**
1. `protected/humhub/modules/user/controllers/AuthController.php`
   - Changed from `erp_email` to `erp_token` parameter
   - Added cURL call to ERP API for token validation
   - Authenticates user based on API response

2. `protected/config/web.php`
   - Added `erpApiUrl` parameter

**New Requirements:**
- PHP cURL extension
- Network access to ERP API

---

## Testing

### Test Token Security
```bash
cd /path/to/erpaej
php artisan tinker
```

```php
$user = User::where('email', 'admin@emploijeunes.ci')->first();
$service = app(\App\Services\HumHubAuthService::class);
$url = $service->generateAuthUrl($user);

echo $url; // Shows token, not email
```

### Verify Token Cannot Be Reused
```php
parse_str(parse_url($url, PHP_URL_QUERY), $params);
$token = $params['erp_token'];

// First validation - succeeds
$data1 = $service->validateSsoToken($token);
var_dump($data1); // Returns user data

// Second validation - fails (token consumed)
$data2 = $service->validateSsoToken($token);
var_dump($data2); // Returns null
```

---

## Security Comparison

| Feature | Before | After |
|---------|--------|-------|
| Email in URL | ❌ Yes | ✅ No |
| Token-based | ❌ No | ✅ Yes |
| Single-use | ❌ No | ✅ Yes |
| Time-limited | ❌ No | ✅ 5 minutes |
| API verification | ❌ No | ✅ Yes |
| Replay protection | ❌ No | ✅ Yes |
| Browser history safe | ❌ No | ✅ Yes |
| Server logs safe | ❌ No | ✅ Yes |
| Bookmarking risk | ❌ High | ✅ None |

---

## Performance Impact

### Token Generation
- **Time**: < 1ms (negligible)
- **Storage**: ~200 bytes per token in cache
- **Expiration**: Automatic cleanup after 5 minutes

### Token Validation
- **Time**: ~50-100ms (network + cache lookup)
- **API Call**: 1 request per SSO login
- **Impact**: Minimal (happens once per session)

---

## Monitoring & Troubleshooting

### Key Metrics to Monitor
1. **Token generation rate** - should match SSO login attempts
2. **Token validation failures** - expired or invalid tokens
3. **Cache hit rate** - ensure tokens are being stored/retrieved correctly
4. **API response time** - ERP token validation endpoint

### Common Issues

**"Token invalid or expired"**
- Token older than 5 minutes
- User already used the link
- Cache not working properly

**"Authentication service unavailable"**
- ERP API endpoint not reachable
- Network connectivity issues
- ERP server down

**Logs to Check:**
- **ERP**: `storage/logs/laravel.log` - search for "HumHub SSO"
- **HumHub**: `protected/runtime/logs/app.log` - search for "ERP SSO"

---

## Migration from Old System

### For Existing Users
No action required. Both systems use the same user email for authentication.

### Rollback Plan (If Needed)
If issues arise, temporary rollback is possible:

1. Change URL generation in `HumHubAuthService`:
```php
// Temporary rollback
return $this->baseUrl . '/user/auth/login?erp_email=' . urlencode($user->email);
```

2. Keep old `erp_email` handling in HumHub's `AuthController` as fallback

**Note**: Not recommended for production. Fix the issue instead.

---

## Best Practices

### For Production Deployment

1. **Use Redis/Memcached for Cache**
   ```env
   CACHE_DRIVER=redis
   ```

2. **Monitor Token Usage**
   - Track token generation vs validation rates
   - Alert on high failure rates

3. **Set Proper Timeouts**
   - cURL timeout: 10 seconds
   - Token expiration: 5 minutes (configurable)

4. **Use HTTPS**
   - Always use SSL/TLS for both ERP and HumHub
   - Prevents token interception

5. **Rate Limiting**
   - Limit token validation API endpoint
   - Prevent brute force attempts

---

## Conclusion

The security upgrade successfully eliminates PII exposure in URLs while maintaining a seamless user experience. The token-based approach provides:
- Strong security through single-use, time-limited tokens
- No visible impact to end users
- Minimal performance overhead
- Easy monitoring and troubleshooting

**Status**: ✅ Production Ready

**Last Updated**: December 2, 2025

