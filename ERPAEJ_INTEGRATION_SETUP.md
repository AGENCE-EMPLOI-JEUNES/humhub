# HumHub Setup for ERPAEJ User Synchronization

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [REST API Installation](#rest-api-installation)
4. [API Configuration](#api-configuration)
5. [User Permissions](#user-permissions)
6. [Testing the Setup](#testing-the-setup)
7. [Security Configuration](#security-configuration)
8. [Troubleshooting](#troubleshooting)

---

## Overview

This guide explains how to configure HumHub to receive user data from the ERPAEJ Laravel application. The integration uses the **HumHub REST API module** to enable programmatic user creation and updates.

### What This Integration Does

- ✅ Receives user data (name, email) from ERPAEJ
- ✅ Creates new HumHub accounts automatically
- ✅ Updates existing user information
- ✅ Maintains sync status tracking
- ✅ Provides secure API authentication

---

## Prerequisites

### Required

- **HumHub**: Version 1.3 or higher
- **PHP**: 7.4 or higher (8.1+ recommended)
- **Web Server**: Apache/Nginx with HTTPS
- **Database**: MySQL/MariaDB
- **Administrator Access**: To HumHub administration panel

### Recommended

- Valid SSL certificate (for HTTPS)
- Dedicated API user account
- Regular backup schedule

---

## REST API Installation

### Step 1: Access HumHub Marketplace

1. Login to HumHub as **Administrator**
2. Navigate to: **Administration** → **Modules**
3. Click on **"Browse online"** or **"Marketplace"**

### Step 2: Install REST API Module

1. Search for **"REST"** or **"REST API"**
2. Find the official **"REST API"** module by HumHub GmbH & Co. KG
3. Click **"Install"**
4. Wait for installation to complete
5. Click **"Enable"** to activate the module

**Alternative Manual Installation:**

```bash
cd /path/to/humhub/protected/modules
git clone https://github.com/humhub/rest.git
cd rest
composer install
```

Then enable via Administration panel.

### Step 3: Verify Installation

1. Go to: **Administration** → **Modules**
2. Find **"REST API"** in the installed modules list
3. Ensure status shows **"Enabled"**
4. Click **"Configure"** to access settings

---

## API Configuration

### Step 1: Enable Authentication Method

1. Go to: **Administration** → **Modules** → **REST API** → **Configure**
2. Under **Authentication Settings**:

#### Option A: Bearer Token (Recommended for Service Accounts)

✅ **Best for**: Server-to-server communication (ERPAEJ → HumHub)

Configuration:
- ☑ Enable **"Bearer Authentication"**
- ☑ Check **"Enable for specific users"**
- Select a dedicated API user account

#### Option B: HTTP Basic Authentication

⚠️ **Alternative**: If Bearer tokens not available

Configuration:
- ☑ Enable **"HTTP Basic Authentication"**
- ☑ Enable for specific API user
- Use username:password in Base64 encoding

### Step 2: Generate API Token

#### For Bearer Token:

1. In REST API configuration, click **"Generate Token"**
2. Select the **API user** from dropdown
3. Click **"Generate"**
4. **IMPORTANT**: Copy the token immediately
   ```
   Example: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC9sb...
   ```
5. Save this token in ERPAEJ `.env` file

#### For HTTP Basic Auth:

1. Use the API user's credentials
2. Encode as Base64: `username:password`
3. Include in Authorization header

### Step 3: Configure API Permissions

1. Go to: **Administration** → **Users** → **Settings**
2. Ensure the following are enabled:
   - ☑ User registration by administrators
   - ☑ User profile updates
   - ☑ Email addresses must be unique

### Step 4: Configure CORS (if needed)

If ERPAEJ and HumHub are on different domains:

Edit `/protected/config/common.php`:

```php
<?php
return [
    'components' => [
        'response' => [
            'on beforeSend' => function ($event) {
                $response = $event->sender;
                $response->headers->add('Access-Control-Allow-Origin', 'https://your-erpaej-domain.com');
                $response->headers->add('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
                $response->headers->add('Access-Control-Allow-Headers', 'Authorization, Content-Type');
            },
        ],
    ],
];
```

⚠️ **Security Note**: Only add CORS if ERPAEJ makes browser-based API calls.

---

## User Permissions

### Create Dedicated API User

1. Go to: **Administration** → **Users** → **Add new user**

2. Create user with details:
   ```
   Username: erpaej_api
   Email: api@erpaej.internal
   Status: Enabled
   ```

3. Assign **Administrator** role (required for user creation)

4. ⚠️ **Security**: This user should:
   - Have a strong password
   - Not be used for regular login
   - Only be used for API operations

### Permission Requirements

The API user needs these permissions:

| Permission | Required For | How to Grant |
|------------|--------------|--------------|
| **Create Users** | Creating new accounts | Admin role |
| **Update Users** | Updating user data | Admin role |
| **View Users** | Checking if user exists | Admin role |
| **Access REST API** | API operations | REST API module settings |

---

## Testing the Setup

### Step 1: Test API Availability

```bash
# Test basic connectivity
curl -X GET "https://your-humhub.com/api/v1/auth/current" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"

# Expected response (200 OK):
{
  "id": 1,
  "username": "erpaej_api",
  "email": "api@erpaej.internal",
  "status": 1
}
```

### Step 2: Test User Creation

```bash
# Test creating a user
curl -X POST "https://your-humhub.com/api/v1/user" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test.user@example.com",
    "username": "Test User",
    "status": 1
  }'

# Expected response (201 Created):
{
  "id": 123,
  "username": "Test User",
  "email": "test.user@example.com",
  "status": 1
}
```

### Step 3: Test from ERPAEJ

```bash
cd /path/to/erpaej
php artisan tinker
```

In Tinker:
```php
$service = app(\App\Services\HumHubAuthService::class);

// Test availability
$service->isAvailable(); // Should return true

// Test user creation
$user = \App\Models\User::first();
$result = $service->createUser($user);
var_dump($result);
```

### Step 4: Run Test Sync

```bash
cd /path/to/erpaej
php artisan humhub:sync-users --email=test@example.com
```

Check HumHub:
1. Go to: **Administration** → **Users**
2. Verify the test user appears in the list

---

## Security Configuration

### 1. SSL/HTTPS Configuration

**Required for Production**

Ensure HumHub is accessible via HTTPS:

```nginx
# Nginx configuration
server {
    listen 443 ssl http2;
    server_name your-humhub.com;
    
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    # ... other configuration
}
```

### 2. API Token Security

✅ **Best Practices**:

- Store tokens in `.env` files (never in code)
- Use environment variables in production
- Rotate tokens every 90 days
- Limit token to specific user
- Monitor API usage logs

### 3. Rate Limiting

Add to `/protected/config/common.php`:

```php
<?php
return [
    'components' => [
        'request' => [
            'class' => 'yii\web\Request',
            'enableCsrfValidation' => false, // For API endpoints
        ],
        'user' => [
            'class' => 'humhub\components\User',
            'authTimeout' => 3600, // 1 hour
        ],
    ],
];
```

### 4. IP Whitelisting (Optional)

Restrict API access to ERPAEJ server IP:

```nginx
# In Nginx configuration
location /api/ {
    allow 192.168.1.100;  # ERPAEJ server IP
    deny all;
    
    # ... rest of configuration
}
```

### 5. Firewall Rules

```bash
# Allow only ERPAEJ server
sudo ufw allow from 192.168.1.100 to any port 443
```

### 6. Audit Logging

Enable API request logging:

1. Go to: **Administration** → **Settings** → **Advanced** → **Logging**
2. Set log level to **"Info"** or **"Debug"**
3. Monitor: `/protected/runtime/logs/app.log`

---

## Troubleshooting

### Issue 1: "401 Unauthorized" Error

**Symptoms**: API requests return 401

**Solutions**:

```bash
# 1. Verify token is correct
curl -X GET "https://your-humhub.com/api/v1/auth/current" \
  -H "Authorization: Bearer YOUR_TOKEN"

# 2. Check if REST API module is enabled
# Go to: Administration → Modules → REST API

# 3. Regenerate token
# In HumHub: REST API → Generate new token

# 4. Check API user status
# In HumHub: Administration → Users → Find API user → Ensure "Enabled"
```

### Issue 2: "403 Forbidden" Error

**Symptoms**: Token valid but operations forbidden

**Solutions**:

1. Check API user has **Administrator** role
2. Go to: **Administration** → **Users** → [API User] → **Permissions**
3. Verify **"Administrator"** checkbox is checked
4. Save and retry

### Issue 3: User Creation Fails with "422 Validation Error"

**Symptoms**: Email or username validation fails

**Solutions**:

```php
// Check in ERPAEJ that user has required fields
$user = User::find(1);
if (empty($user->email) || empty($user->name)) {
    // Fix missing data
}

// Ensure email is unique in HumHub
// Go to: Administration → Settings → User → Enable "Unique email addresses"
```

### Issue 4: SSL Certificate Errors

**Symptoms**: `SSL certificate problem: unable to get local issuer certificate`

**Development Solution** (⚠️ Not for production):

Edit `app/Services/HumHubAuthService.php`:

```php
$response = Http::withoutVerifying()->withHeaders([
    'Authorization' => 'Bearer ' . $this->apiKey,
])->get($this->apiUrl . '/user');
```

**Production Solution**:

```bash
# Update CA certificates
sudo apt-get update
sudo apt-get install ca-certificates

# Or provide certificate path
CURL_CA_BUNDLE=/path/to/cacert.pem
```

### Issue 5: Module Not Found

**Symptoms**: REST API module not appearing

**Solutions**:

```bash
# Manual installation
cd /path/to/humhub/protected/modules
git clone https://github.com/humhub/rest.git
cd rest
composer install

# Set correct permissions
chmod -R 755 /path/to/humhub/protected/modules/rest

# Clear cache
cd /path/to/humhub/protected
php yii cache/flush-all
```

### Issue 6: API Endpoints Return 404

**Symptoms**: `/api/v1/*` returns 404 Not Found

**Solutions**:

Check URL rewriting:

**For Nginx:**
```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}
```

**For Apache:**
```apache
# Ensure mod_rewrite is enabled
sudo a2enmod rewrite

# Check .htaccess file exists
cat /path/to/humhub/.htaccess
```

### Checking Logs

```bash
# HumHub application logs
tail -f /path/to/humhub/protected/runtime/logs/app.log

# Web server logs (Nginx)
tail -f /var/log/nginx/error.log

# Web server logs (Apache)
tail -f /var/log/apache2/error.log

# ERPAEJ logs
tail -f /path/to/erpaej/storage/logs/laravel.log | grep HumHub
```

---

## API Endpoints Reference

### User Endpoints

| Method | Endpoint | Purpose | Auth Required |
|--------|----------|---------|---------------|
| GET | `/api/v1/user` | Get all users | ✅ Bearer |
| GET | `/api/v1/user?email={email}` | Get user by email | ✅ Bearer |
| GET | `/api/v1/user/{id}` | Get user by ID | ✅ Bearer |
| POST | `/api/v1/user` | Create user | ✅ Bearer + Admin |
| PUT | `/api/v1/user/{id}` | Update user | ✅ Bearer + Admin |
| DELETE | `/api/v1/user/{id}` | Delete user | ✅ Bearer + Admin |

### Authentication Endpoints

| Method | Endpoint | Purpose | Auth Required |
|--------|----------|---------|---------------|
| POST | `/api/v1/auth/login` | Login with credentials | ❌ None |
| GET | `/api/v1/auth/current` | Get current user | ✅ Bearer |

---

## Production Checklist

Before deploying to production:

- [ ] REST API module installed and enabled
- [ ] Bearer token generated and secured
- [ ] Dedicated API user created with admin role
- [ ] HTTPS/SSL properly configured
- [ ] API token stored in `.env` file
- [ ] CORS configured (if needed)
- [ ] Rate limiting implemented
- [ ] Logging enabled
- [ ] Test sync completed successfully
- [ ] Backup procedures in place
- [ ] Monitoring configured
- [ ] Documentation reviewed
- [ ] Security audit completed

---

## Monitoring & Maintenance

### Regular Tasks

| Task | Frequency | Action |
|------|-----------|--------|
| **Token Rotation** | Every 90 days | Regenerate API token |
| **Log Review** | Weekly | Check for failed sync attempts |
| **User Audit** | Monthly | Verify synced users match ERPAEJ |
| **Backup Verification** | Weekly | Ensure backups include user data |
| **Module Updates** | Monthly | Update REST API module |

### Monitoring Metrics

Track these metrics:

- API request success rate
- User creation success rate
- Average sync duration
- Failed sync attempts
- API response times

---

## Additional Resources

- **HumHub Documentation**: https://docs.humhub.org/
- **REST API Module**: https://marketplace.humhub.com/module/rest
- **REST API Docs**: https://marketplace.humhub.com/module/rest/docs
- **HumHub Community**: https://community.humhub.com/
- **GitHub Issues**: https://github.com/humhub/rest/issues

---

## Support

For issues specific to:

- **HumHub REST API**: Check module documentation
- **ERPAEJ Integration**: See `docs/HUMHUB_USER_SYNC.md` in ERPAEJ project
- **API Errors**: Check both HumHub and ERPAEJ logs

---

**Setup Complete!** 🎉

Once configured, users will automatically sync from ERPAEJ to HumHub.

---

**Last Updated**: December 9, 2025  
**Version**: 1.0.0  
**For**: HumHub 1.3+
