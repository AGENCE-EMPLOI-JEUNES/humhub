# REST API Configuration Template

## API Authentication Configuration

### Bearer Token Setup (Recommended)

**Location**: Administration → Modules → REST API → Configure

#### Settings to Enable:

```
☑ Enable Bearer Authentication
☑ Enable for specific users
   → Select: erpaej_api (or your API user)
```

#### Generate Token:

1. Click **"Generate Token"**
2. Select user: `erpaej_api`
3. Copy generated token
4. Save securely

---

## User Configuration

### Create API User

**Location**: Administration → Users → Add new user

```
Username: erpaej_api
Email: api@erpaej.internal
Password: [Strong password - store securely]
Status: ☑ Enabled
```

#### Assign Permissions:

**Location**: Administration → Users → [erpaej_api] → Edit

```
☑ Administrator
   (Required for creating/updating users via API)
```

---

## Module Settings

### REST API Module

**Location**: Administration → Modules → REST API

```
Status: ☑ Enabled
```

#### Configure Authentication:

```
Authentication Methods:
  ☑ Bearer Authentication
  ☐ HTTP Basic Authentication (optional fallback)
  ☐ Query Parameter Authentication (not recommended)

User Access:
  ☑ Enable for specific users
     → erpaej_api
```

---

## User Settings

### Email Uniqueness

**Location**: Administration → Settings → User

```
☑ Email addresses must be unique
```

This ensures ERPAEJ users sync correctly without duplicates.

---

## Security Settings

### Password Policy

**Location**: Administration → Settings → Security

```
☑ Enable password policy
Minimum password length: 8
☑ Require special character
☑ Require number
```

### Session Timeout

```
Session timeout: 3600 seconds (1 hour)
```

---

## CORS Configuration (If Needed)

**File**: `/protected/config/common.php`

```php
<?php
return [
    'components' => [
        'response' => [
            'on beforeSend' => function ($event) {
                $response = $event->sender;
                // Only if ERPAEJ is on different domain
                $response->headers->add('Access-Control-Allow-Origin', 'https://erpaej-domain.com');
                $response->headers->add('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
                $response->headers->add('Access-Control-Allow-Headers', 'Authorization, Content-Type');
            },
        ],
    ],
];
```

⚠️ **Note**: Only needed if browser makes direct API calls.

---

## Logging Configuration

### Enable API Logging

**Location**: Administration → Settings → Advanced → Logging

```
Log Level: Info
☑ Enable file logging
Log path: /protected/runtime/logs/
```

### What Gets Logged:

- API authentication attempts
- User creation/update operations
- Failed API requests
- Error messages

---

## URL Rewriting Configuration

### For Nginx

**File**: `/etc/nginx/sites-available/humhub`

```nginx
server {
    listen 443 ssl http2;
    server_name your-humhub.com;
    
    root /path/to/humhub;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    # API endpoint handling
    location /api/ {
        try_files $uri $uri/ /index.php?$args;
    }
    
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### For Apache

**File**: `.htaccess` (should exist by default)

Ensure these lines are present:

```apache
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php
```

---

## Testing Configuration

### Test 1: API Availability

```bash
curl -X GET "https://your-humhub.com/api/v1/auth/current" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Expected Response (200 OK)**:
```json
{
  "id": 1,
  "username": "erpaej_api",
  "email": "api@erpaej.internal",
  "status": 1
}
```

### Test 2: User Creation Permission

```bash
curl -X POST "https://your-humhub.com/api/v1/user" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test.sync@example.com",
    "username": "Test Sync User",
    "status": 1
  }'
```

**Expected Response (201 Created)**:
```json
{
  "id": 123,
  "username": "Test Sync User",
  "email": "test.sync@example.com",
  "status": 1,
  "created_at": "2025-12-09T10:30:00Z"
}
```

### Test 3: User Query

```bash
curl -X GET "https://your-humhub.com/api/v1/user?email=test.sync@example.com" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Expected Response (200 OK)**:
Returns user data if exists, or 404 if not found.

---

## Configuration Verification Checklist

Use this checklist to verify configuration:

### REST API Module
- [ ] Module installed from Marketplace
- [ ] Module enabled in Administration
- [ ] Version 0.9.0 or higher

### Authentication
- [ ] Bearer Authentication enabled
- [ ] API token generated
- [ ] Token tested and working
- [ ] API user created
- [ ] API user has Administrator role

### Security
- [ ] HTTPS/SSL configured
- [ ] Email uniqueness enabled
- [ ] Password policy configured
- [ ] Session timeout set

### Logging
- [ ] Log level set to Info or Debug
- [ ] File logging enabled
- [ ] Logs directory writable

### Network
- [ ] URL rewriting configured
- [ ] API endpoints accessible
- [ ] CORS configured (if needed)
- [ ] Firewall rules set

### Testing
- [ ] Auth endpoint tested (200 OK)
- [ ] User creation tested (201 Created)
- [ ] User query tested (200 OK)
- [ ] Test user visible in HumHub

---

## Environment Variables (For Reference)

These should be configured in **ERPAEJ**, not HumHub:

```env
# ERPAEJ .env file
HUMHUB_URL=https://your-humhub.com
HUMHUB_API_URL=https://your-humhub.com/api/v1
HUMHUB_API_KEY=your-generated-token
```

---

## Backup Configuration

Before making changes, backup:

```bash
# Database
mysqldump -u humhub_user -p humhub_db > backup_$(date +%Y%m%d).sql

# Configuration files
tar -czf humhub_config_$(date +%Y%m%d).tar.gz /path/to/humhub/protected/config/

# Entire installation (optional)
tar -czf humhub_full_$(date +%Y%m%d).tar.gz /path/to/humhub/
```

---

## Rollback Procedure

If something goes wrong:

1. **Disable REST API Module**:
   - Administration → Modules → REST API → Disable

2. **Restore Configuration**:
   ```bash
   tar -xzf humhub_config_backup.tar.gz -C /
   ```

3. **Restore Database** (if needed):
   ```bash
   mysql -u humhub_user -p humhub_db < backup.sql
   ```

4. **Clear Cache**:
   ```bash
   cd /path/to/humhub/protected
   php yii cache/flush-all
   ```

---

## Maintenance Schedule

| Task | Frequency | Action |
|------|-----------|--------|
| **Token Rotation** | Every 90 days | Regenerate and share new token |
| **Log Review** | Weekly | Check API access logs |
| **Module Update** | Monthly | Update REST API module |
| **Permission Audit** | Monthly | Verify API user permissions |
| **Backup** | Daily | Automated database backup |

---

## Additional Resources

- **REST API Documentation**: https://marketplace.humhub.com/module/rest/docs
- **HumHub Admin Guide**: https://docs.humhub.org/docs/admin/introduction
- **Yii2 Framework**: https://www.yiiframework.com/doc/guide/2.0/en

---

**Configuration Template Version**: 1.0.0  
**Last Updated**: December 9, 2025  
**Compatible With**: HumHub 1.3 - 1.18
