# Apache Deployment Guide for OMS Launch

This guide covers deploying OMS Launch on Apache web server in different configurations.

## Quick Setup (Subdirectory Deployment)

If you have OMS Launch in a subdirectory (e.g., `/var/www/html/oms_launch`):

### 1. Update Your .env File

Edit `.env` and set the correct `BASE_URL`:

```bash
# If your URL is http://example.com/oms_launch
BASE_URL=http://example.com/oms_launch

# For HTTPS:
BASE_URL=https://example.com/oms_launch

# For localhost testing:
BASE_URL=http://localhost/oms_launch
```

### 2. Ensure Apache Modules Are Enabled

```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

### 3. Verify .htaccess is Working

The included `.htaccess` file should work automatically if `AllowOverride All` is set.

To verify, check your Apache configuration:

```bash
# Ubuntu/Debian
sudo nano /etc/apache2/sites-available/000-default.conf

# Look for or add:
<Directory /var/www/html>
    AllowOverride All
</Directory>
```

If you need to change it:

```bash
sudo systemctl restart apache2
```

### 4. Set Correct Permissions

```bash
cd /var/www/html/oms_launch
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 777 uploads/ content/ logs/
```

### 5. Test Your Setup

Visit your site:
- Main page: `http://example.com/oms_launch/`
- Test API: `http://example.com/oms_launch/api/upload_content.php`

Run the setup verification:
```bash
php test_setup.php
```

---

## VirtualHost Deployment (Recommended for Production)

For cleaner URLs and better performance, use a VirtualHost configuration.

### Option A: Subdomain (e.g., oms.example.com)

**1. Create VirtualHost configuration:**

```bash
sudo nano /etc/apache2/sites-available/oms_launch.conf
```

**2. Add this configuration:**

```apache
<VirtualHost *:80>
    ServerName oms.example.com
    DocumentRoot /var/www/oms_launch

    <Directory /var/www/oms_launch>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/oms_launch_error.log
    CustomLog ${APACHE_LOG_DIR}/oms_launch_access.log combined
</VirtualHost>
```

**3. Enable site and restart:**

```bash
sudo a2ensite oms_launch
sudo systemctl restart apache2
```

**4. Update .env:**

```bash
BASE_URL=http://oms.example.com
```

**5. Update DNS:**

Add an A record for `oms.example.com` pointing to your server's IP.

### Option B: SSL/HTTPS with Let's Encrypt (Recommended)

**1. Install Certbot:**

```bash
sudo apt update
sudo apt install certbot python3-certbot-apache
```

**2. Get SSL certificate:**

```bash
sudo certbot --apache -d oms.example.com
```

**3. Certbot will automatically configure SSL. Update .env:**

```bash
BASE_URL=https://oms.example.com
```

**4. Verify auto-renewal:**

```bash
sudo certbot renew --dry-run
```

---

## Troubleshooting

### Issue: API calls return 404

**Solution:** Ensure mod_rewrite is enabled and .htaccess is being read.

```bash
# Check if mod_rewrite is enabled
apache2ctl -M | grep rewrite

# Enable it if missing
sudo a2enmod rewrite
sudo systemctl restart apache2

# Check AllowOverride in your Apache config
grep -r "AllowOverride" /etc/apache2/
```

### Issue: "No input file specified" error

**Solution:** Update your PHP configuration for Apache.

```bash
# Edit PHP-FPM configuration (if using PHP-FPM)
sudo nano /etc/php/8.1/fpm/php.ini

# Find and set:
cgi.fix_pathinfo=0

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

### Issue: Upload size limit exceeded

**Solution:** Increase PHP upload limits.

```bash
# Edit php.ini
sudo nano /etc/php/8.1/apache2/php.ini

# Find and update:
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M

# Restart Apache
sudo systemctl restart apache2
```

### Issue: Permission denied errors

**Solution:** Set correct ownership and permissions.

```bash
# Set ownership to web server user
sudo chown -R www-data:www-data /var/www/html/oms_launch

# Set correct permissions
sudo chmod -R 755 /var/www/html/oms_launch
sudo chmod -R 777 /var/www/html/oms_launch/uploads
sudo chmod -R 777 /var/www/html/oms_launch/content
sudo chmod -R 777 /var/www/html/oms_launch/logs
```

### Issue: .htaccess not working

**Solution:** Check if AllowOverride is enabled.

```bash
# Edit your site config
sudo nano /etc/apache2/sites-available/000-default.conf

# Add or modify:
<Directory /var/www/html>
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# Restart Apache
sudo systemctl restart apache2
```

### Issue: Base URL is wrong in JavaScript

**Solution:** Verify your .env file has the correct BASE_URL.

```bash
# Check current setting
grep BASE_URL .env

# Update if needed
nano .env

# Make sure it matches your actual URL (including subdirectory if applicable)
BASE_URL=http://example.com/oms_launch
```

---

## Security Best Practices

### 1. Disable Directory Listing

Already done in `.htaccess`, but verify:

```apache
Options -Indexes
```

### 2. Protect Sensitive Files

Already configured in `.htaccess` to block:
- `.env` files
- `.git` directory
- `config/` directory
- `includes/` directory
- `schema.sql`

### 3. Enable HTTPS

Always use HTTPS in production:

```bash
sudo certbot --apache -d oms.example.com
```

### 4. Regular Security Updates

```bash
sudo apt update
sudo apt upgrade
```

### 5. Restrict config/includes Directories

Add to your Apache config:

```apache
<DirectoryMatch "(config|includes)">
    Require all denied
</DirectoryMatch>
```

---

## Performance Optimization

### 1. Enable Compression

```bash
sudo a2enmod deflate
```

Add to VirtualHost:

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>
```

### 2. Enable Browser Caching

Add to `.htaccess`:

```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### 3. Enable OPcache

```bash
sudo nano /etc/php/8.1/apache2/php.ini

# Enable and configure OPcache:
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

---

## Deployment Checklist

- [ ] PostgreSQL installed and configured
- [ ] Database schema imported
- [ ] `.env` file created with correct credentials
- [ ] `BASE_URL` set correctly in `.env`
- [ ] Apache mod_rewrite enabled
- [ ] Apache mod_headers enabled
- [ ] AllowOverride All configured
- [ ] File permissions set (755 for files, 777 for upload dirs)
- [ ] Ownership set to www-data (or appropriate web user)
- [ ] PHP extensions installed (pdo_pgsql, curl, zip, etc.)
- [ ] Upload limits increased in php.ini
- [ ] SSL certificate installed (production only)
- [ ] Firewall configured to allow HTTP/HTTPS
- [ ] Test API endpoints working
- [ ] Test content upload and launch
- [ ] Review error logs: `/var/log/apache2/error.log`

---

## Testing After Deployment

### 1. Test Setup Script

```bash
php test_setup.php
```

All checks should pass.

### 2. Test Content Upload

```bash
./test_api.sh http://example.com/oms_launch
# OR for subdomain:
./test_api.sh http://oms.example.com
```

### 3. Manual API Test

```bash
curl -X POST http://example.com/oms_launch/api/upload_content.php \
  -F "account_id=1" \
  -F "title=Test Content" \
  -F "upload_type=raw_html" \
  -F "html_content=<html><body><h1>Test</h1></body></html>"
```

### 4. Check Logs

```bash
# Application logs
tail -f logs/app.log

# Apache error logs
sudo tail -f /var/log/apache2/error.log

# Apache access logs
sudo tail -f /var/log/apache2/access.log
```

---

## Common Apache Configurations

### Ubuntu/Debian PHP-FPM

```apache
<VirtualHost *:80>
    ServerName oms.example.com
    DocumentRoot /var/www/oms_launch

    <Directory /var/www/oms_launch>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.1-fpm.sock|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
```

### CentOS/RHEL

```apache
<VirtualHost *:80>
    ServerName oms.example.com
    DocumentRoot /var/www/oms_launch

    <Directory /var/www/oms_launch>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:fcgi://127.0.0.1:9000"
    </FilesMatch>
</VirtualHost>
```

---

## Need Help?

If you encounter issues:

1. Check Apache error logs: `sudo tail -f /var/log/apache2/error.log`
2. Check PHP error logs: `sudo tail -f /var/log/php8.1-fpm.log`
3. Check application logs: `tail -f logs/app.log`
4. Verify `.env` configuration: `cat .env`
5. Test database connection: `php test_setup.php`
6. Check file permissions: `ls -la`

For more help, refer to `README.md` and `QUICK_START.md`.
