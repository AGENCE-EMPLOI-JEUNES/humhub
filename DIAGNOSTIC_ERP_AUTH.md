# HumHub ERP Authentication Diagnostic Guide

## Issue: Redirecting to Login Instead of Authenticating

When accessing `http://humhub.test/auth_user/admin@emploijeunes.ci`, you're being redirected to the login page.

## Step-by-Step Diagnosis

### Step 1: Check if User Exists in HumHub

Run this SQL query in your HumHub database:

```sql
SELECT 
    id,
    username,
    email,
    status,
    auth_mode,
    created_at,
    updated_at
FROM user 
WHERE email = 'admin@emploijeunes.ci';
```

**Expected Result:** Should return one row with user information.

**If NO rows returned:**
- ❌ User doesn't exist in HumHub
- **Solution:** Create the user in HumHub first

**If row exists, check the `status` column:**

### Step 2: Check User Status

The `status` field must equal `1` (STATUS_ENABLED) for authentication to work.

Status values:
- `0` = Need approval
- `1` = Enabled (✅ Required)
- `2` = Disabled

**If status is not 1:**

```sql
-- Enable the user
UPDATE user 
SET status = 1 
WHERE email = 'admin@emploijeunes.ci';
```

### Step 3: Check HumHub Logs

View the logs to see detailed error messages:

```bash
# Go to HumHub directory
cd /path/to/humhub

# View logs in real-time
tail -f protected/runtime/logs/app.log

# Or search for specific user
grep "admin@emploijeunes.ci" protected/runtime/logs/app.log

# Look for "ERP Auth" entries
grep "ERP Auth" protected/runtime/logs/app.log
```

### Step 4: Test the Endpoint Directly

```bash
# Test if URL routing works
curl -I http://humhub.test/auth_user/admin@emploijeunes.ci

# Should return 302 redirect, not 404
```

### Step 5: Clear HumHub Cache

Sometimes cache issues can cause problems:

```bash
cd /path/to/humhub
php yii cache/flush-all
```

### Step 6: Verify Files Are in Place

Check that these files exist:

```bash
# Check ErpAuth client
ls -la protected/humhub/modules/user/authclient/ErpAuth.php

# Check ErpAuthController
ls -la protected/humhub/modules/user/controllers/ErpAuthController.php

# Check config
cat protected/humhub/modules/user/config.php | grep auth_user
```

## Common Issues and Solutions

### Issue 1: User Not Found

**Symptom:** Log shows "User not found"

**Solution:**

```sql
-- Check if user exists with different email
SELECT id, username, email FROM user WHERE username LIKE '%admin%';

-- If user exists with different email, update it
UPDATE user SET email = 'admin@emploijeunes.ci' WHERE username = 'admin';
```

### Issue 2: User Status Not Enabled

**Symptom:** Log shows "User not enabled"

**Solution:**

```sql
-- Enable the user
UPDATE user SET status = 1 WHERE email = 'admin@emploijeunes.ci';

-- Verify
SELECT id, username, email, status FROM user WHERE email = 'admin@emploijeunes.ci';
```

### Issue 3: Authentication Mode Mismatch

**Symptom:** User exists but login fails

**Solution:**

```sql
-- Check auth_mode
SELECT id, username, email, auth_mode FROM user WHERE email = 'admin@emploijeunes.ci';

-- The auth_mode can be 'local', 'ldap', etc.
-- For ERP auth to work, any auth_mode should work as ErpAuth looks up by email
```

### Issue 4: URL Routing Not Working

**Symptom:** 404 error on /auth_user/

**Solution:**

```bash
# Clear cache
php yii cache/flush-all

# Check .htaccess or nginx config
# Make sure URL rewriting is enabled
```

## Quick Fix Script

Create and run this PHP script in HumHub's protected directory:

```php
<?php
// diagnostic.php
require(__DIR__ . '/config/bootstrap.php');

$email = 'admin@emploijeunes.ci';

echo "Checking user: {$email}\n\n";

$user = \humhub\modules\user\models\User::findOne(['email' => $email]);

if (!$user) {
    echo "❌ User NOT FOUND in database\n";
    echo "\nSearching for similar users...\n";
    $users = \humhub\modules\user\models\User::find()->all();
    foreach ($users as $u) {
        echo "  - ID: {$u->id}, Username: {$u->username}, Email: {$u->email}\n";
    }
} else {
    echo "✅ User FOUND\n";
    echo "ID: {$user->id}\n";
    echo "Username: {$user->username}\n";
    echo "Email: {$user->email}\n";
    echo "Status: {$user->status} (1 = enabled, required)\n";
    echo "Auth Mode: {$user->auth_mode}\n";
    
    if ($user->status == \humhub\modules\user\models\User::STATUS_ENABLED) {
        echo "\n✅ Status is ENABLED - User should be able to login\n";
    } else {
        echo "\n❌ Status is NOT ENABLED\n";
        echo "Run this SQL to enable:\n";
        echo "UPDATE user SET status = 1 WHERE email = '{$email}';\n";
    }
}
```

Run it:

```bash
cd /path/to/humhub/protected
php diagnostic.php
```

## Testing After Fixes

After making any changes:

1. **Clear cache:**
   ```bash
   php yii cache/flush-all
   ```

2. **Check logs (in separate terminal):**
   ```bash
   tail -f protected/runtime/logs/app.log | grep "ERP Auth"
   ```

3. **Test the URL:**
   ```
   http://humhub.test/auth_user/admin@emploijeunes.ci
   ```

4. **Expected log output (success):**
   ```
   ERP Auth: Starting authentication for - admin@emploijeunes.ci
   ERP Auth: User found - ID: X, Username: admin, Status: 1
   ERP Auth: ErpAuth client authenticated successfully
   ERP Auth: Login attempt result: success
   ERP Auth: User logged in successfully - admin@emploijeunes.ci
   ```

5. **Expected result:**
   - Redirect to: `http://humhub.test/dashboard/dashboard`
   - User is logged in

## Still Not Working?

If still having issues, provide:

1. Output of the user query (Step 1)
2. Last 20 lines of log mentioning "ERP Auth"
3. Output of diagnostic script
4. Screenshot of error message (if any)

## Create Test User

If you want to create a test user for debugging:

```sql
-- Create a simple test user
INSERT INTO user (guid, username, email, status, auth_mode, created_at, updated_at)
VALUES (
    UUID(),
    'testuser',
    'test@emploijeunes.ci',
    1,  -- STATUS_ENABLED
    'local',
    NOW(),
    NOW()
);

-- Test with this user
-- http://humhub.test/auth_user/test@emploijeunes.ci
```

## Enable Debug Mode

For more detailed error messages:

Edit `protected/config/common.php`:

```php
return [
    'params' => [
        'enableDebugMode' => true,
    ],
];
```

Then test again and check for more detailed error messages on screen.

---

**Next Steps:**
1. Run the SQL query to check user status
2. Check the logs during authentication attempt
3. Fix any issues found
4. Test again

