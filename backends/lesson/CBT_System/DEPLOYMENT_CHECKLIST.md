# CBT System Deployment Checklist

## Before Uploading to Hosting Server

### 1. Database Configuration
- [ ] Update `config/config.php` with your hosting database credentials:
  ```php
  define('DB_HOST', 'your_hosting_db_host');
  define('DB_USER', 'your_hosting_db_username');
  define('DB_PASS', 'your_hosting_db_password');
  define('DB_NAME', 'your_hosting_db_name');
  ```

### 2. Site URL Configuration
- [ ] Update `SITE_URL` in `config/config.php` to match your domain:
  ```php
  define('SITE_URL', 'https://yourdomain.com/path/to/CBT_System');
  ```

### 3. Database Setup
- [ ] Create the database on your hosting server
- [ ] Import the database structure (tables: morning_students, afternoon_students, etc.)
- [ ] Ensure all required tables exist

### 4. File Permissions
- [ ] Set uploads directory to writable (755 or 775)
- [ ] Ensure config and includes directories are readable (644)

## After Uploading

### 1. Test Connection
- [ ] Visit: `yourdomain.com/path/to/CBT_System/test_connection.php`
- [ ] Check all tests pass (PHP version, file paths, database connection, etc.)

### 2. Test Login Page
- [ ] Visit: `yourdomain.com/path/to/CBT_System/login.php`
- [ ] If errors occur, add `?debug=1` to URL for debug information

### 3. Common Issues & Solutions

#### Issue: "Config file not found"
**Solution:** Check file paths and ensure all files uploaded correctly

#### Issue: "Database connection failed"
**Solution:** 
- Verify database credentials in config.php
- Check if database exists
- Ensure database user has proper permissions

#### Issue: "Permission denied" errors
**Solution:**
- Set proper file permissions (755 for directories, 644 for files)
- Make uploads directory writable (775 or 777)

#### Issue: Session not working
**Solution:**
- Check if sessions are enabled on hosting
- Verify session directory is writable

### 4. Security Settings (Production)
- [ ] Set `DEBUG_MODE` to `false` in config.php
- [ ] Ensure error reporting is disabled
- [ ] Use HTTPS if available
- [ ] Set secure session cookies

### 5. File Structure Verification
Ensure these files and directories exist:
```
CBT_System/
├── config/
│   └── config.php
├── includes/
│   └── Database.php
├── uploads/
├── login.php
├── dashboard.php
├── test_connection.php
└── [other files...]
```

## Testing Steps

1. **Run test_connection.php** - This will show you exactly what's working and what's not
2. **Try login.php?debug=1** - Shows debug information if there are issues
3. **Check error logs** - Your hosting provider should have error logs available
4. **Test with a sample user** - Create a test user in the database to verify login works

## Support

If you continue to have issues:
1. Check your hosting provider's PHP version (should be 7.4+)
2. Verify MySQL/MariaDB is available
3. Check hosting error logs
4. Contact your hosting provider for PHP/MySQL support 