# 🔐 Enhanced Evernode Secure Setup Guide

**IMPORTANT:** This guide shows you how to set up Enhanced Evernode with YOUR specific information securely. No personal data is shared publicly.

## 🎯 What This Protects

✅ **Your Xahau addresses** - Never hardcoded in public files  
✅ **Your admin passwords** - Securely hashed and stored locally  
✅ **Your domain information** - Auto-detected or user-configured  
✅ **Your host specifications** - Real data from your Evernode setup  
✅ **Your commission settings** - Private configuration only

## 🚀 Quick Start (Recommended)

### **Option 1: Automated Secure Setup**

```bash
# 1. Clone the repository
git clone https://github.com/your-username/evernode-enhanced-setup.git
cd evernode-enhanced-setup

# 2. Run the secure configuration script
chmod +x setup-secure-config.sh
./setup-secure-config.sh

# 3. Run the main installation
chmod +x setup-unified-system.sh
sudo ./setup-unified-system.sh
```

The script will prompt you for:
- Your domain (auto-detected)
- Your Xahau address (from Evernode CLI)
- Your instance limit (from Evernode CLI)  
- Commission settings (optional)

## 🔧 Manual Configuration

### **Step 1: Create Your Configuration**

```bash
# Copy the template
cp config-template.php /var/www/html/config/config.php

# Edit with your information
sudo nano /var/www/html/config/config.php
```

### **Step 2: Configure Your Values**

```php
// Replace these with YOUR information:
define('HOST_DOMAIN', 'your-domain.com');
define('XAHAU_ADDRESS', 'rYourXahauAddress123...');
define('EVERNODE_INSTANCE_LIMIT', 5); // Your actual limit

// Generate secure password hash:
// php -r "echo password_hash('your_password', PASSWORD_ARGON2ID);"
define('ADMIN_PASSWORD_HASH', 'your_generated_hash');

// Generate random API secret:
// openssl rand -hex 32
define('API_SECRET_KEY', 'your_random_string');
```

### **Step 3: Secure File Permissions**

```bash
# Set secure permissions
sudo chown -R www-data:www-data /var/www/html/
sudo chmod 700 /var/www/html/config/
sudo chmod 600 /var/www/html/config/config.php
```

## 🔑 Password Security

### **Generate Secure Admin Password**

```bash
# Generate a random password
openssl rand -base64 20

# Create password hash for config file
php -r "echo password_hash('your_chosen_password', PASSWORD_ARGON2ID);"
```

### **Where Passwords Are Used**

1. **Landing Page Admin Access** - Uses password verification API
2. **dApp Manager** - Uses secure session authentication  
3. **Cluster Manager** - Uses role-based access control

### **Password Best Practices**

✅ Use unique, randomly generated passwords  
✅ Store passwords securely (never in plain text)  
✅ Use password hashing (Argon2ID recommended)  
✅ Implement session timeouts (1 hour default)  
✅ Add rate limiting for login attempts

## 🏗️ File Structure

```
evernode-enhanced-setup/
├── 📁 config/
│   ├── config-template.php      # Template (safe for GitHub)
│   ├── config.php              # Your config (never commit!)
│   └── config-functions.php    # Shared functions
├── 📁 api/
│   ├── instance-count.php      # Uses config values
│   ├── host-info.php          # Uses config values  
│   └── admin-auth.php         # Secure authentication
├── 📄 index-template.html      # Generic landing page
├── 📄 setup-secure-config.sh   # Configuration script
└── 📄 .gitignore              # Protects your config
```

## 🛡️ Security Features

### **Configuration Security**
- No hardcoded credentials in public files
- Environment-based configuration
- Secure file permissions (600/700)
- Auto-generated passwords and secrets

### **Authentication Security**  
- Password hashing (Argon2ID)
- Session-based authentication
- Session timeout (1 hour)
- CSRF protection via API secrets

### **API Security**
- Input validation and sanitization  
- Rate limiting on login attempts
- CORS headers properly configured
- Error messages don't leak information

### **File Security**
- Config files outside web root access
- Proper file permissions
- .gitignore protects sensitive files
- Database files secured

## 📋 Pre-Installation Checklist

**Before running the setup:**

```bash
# 1. Verify Evernode is working
evernode config account
evernode totalins

# 2. Check web server
sudo systemctl status nginx
sudo systemctl status php*-fpm

# 3. Verify domain/DNS
nslookup your-domain.com

# 4. Check SSL certificate  
sudo certbot certificates

# 5. Test basic PHP
echo "<?php phpinfo(); ?>" | sudo tee /var/www/html/test.php
curl http://localhost/test.php
sudo rm /var/www/html/test.php
```

## 🧪 Testing Your Setup

### **Test Configuration**

```bash
# Test configuration loading
php -f /var/www/html/config/config.php

# Test API endpoints  
curl http://localhost/api/?endpoint=health
curl http://localhost/api/?endpoint=instance-count
curl http://localhost/api/?endpoint=host-info
```

### **Test Admin Authentication**

```bash
# Test admin login (replace with your password)
curl -X POST http://localhost/api/?action=login \
  -H "Content-Type: application/json" \
  -d '{"password":"your_admin_password"}'

# Test admin session check
curl http://localhost/api/?action=check
```

### **Test Landing Page**

1. Open your domain in browser
2. Verify tenant features work
3. Test admin access with your password
4. Confirm role switching works
5. Check all navigation links

## 🔧 Customization Options

### **Branding**
```bash
# Update branding in index-template.html
sed -i 's/Enhanced Evernode/Your Brand Name/g' index-template.html
```

### **Styling**
```bash
# Customize colors in CSS
# Change #00ff88 to your brand color
```

### **Features**
```bash
# Enable/disable features in config.php
define('ENABLE_COMMISSION_TRACKING', true);
define('ENABLE_HOST_DISCOVERY', true);
define('ENABLE_CLUSTER_MANAGER', true);
```

## 🚨 Common Issues & Solutions

### **Configuration Not Loading**
```bash
# Check file permissions
ls -la /var/www/html/config/

# Check PHP errors
sudo tail -f /var/log/nginx/error.log
```

### **Admin Login Fails**
```bash
# Verify password hash
php -r "var_dump(password_verify('your_password', 'your_hash'));"

# Check session permissions
sudo chown -R www-data:www-data /var/lib/php/sessions/
```

### **API Errors**
```bash
# Check API router
curl http://localhost/api/?endpoint=health

# Verify database permissions
sudo chown www-data:www-data /var/www/html/data/
```

## 📂 .gitignore Template

**Add this to your .gitignore:**

```gitignore
# Enhanced Evernode - Protect sensitive files
/config/config.php
/data/
/cache/
*.log
.env
.credentials

# User-specific files
/backup/
/temp/
node_modules/
.DS_Store
Thumbs.db
```

## 🔄 Updates & Maintenance

### **Updating Your Setup**
```bash
# Backup your config first
cp /var/www/html/config/config.php ~/config-backup.php

# Pull latest changes
git pull origin main

# Restore your config
cp ~/config-backup.php /var/www/html/config/config.php

# Run any update scripts
./update-secure-setup.sh
```

### **Rotating Credentials**
```bash
# Generate new admin password
NEW_PASSWORD=$(openssl rand -base64 20)
NEW_HASH=$(php -r "echo password_hash('$NEW_PASSWORD', PASSWORD_ARGON2ID);")

# Update config file
sudo sed -i "s/ADMIN_PASSWORD_HASH', '.*'/ADMIN_PASSWORD_HASH', '$NEW_HASH'/" /var/www/html/config/config.php

echo "New password: $NEW_PASSWORD"
```

## 🆘 Support & Troubleshooting

### **Log Files**
```bash
# Web server logs
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log

# PHP logs  
sudo tail -f /var/log/php*-fpm.log

# Application logs
tail -f /var/www/html/data/logs/app.log
```

### **Debug Mode**
```bash
# Enable debug mode in config.php
define('DEBUG_MODE', true);

# Check debug output
curl http://localhost/api/?endpoint=health&debug=1
```

### **Getting Help**
- 📝 GitHub Issues: Report bugs and request features
- 💬 Community: Join discussions about Enhanced Evernode  
- 📧 Direct Support: For configuration assistance

## ✅ Production Readiness Checklist

**Before going live:**

- [ ] All default passwords changed
- [ ] Configuration file secured (600 permissions)  
- [ ] SSL certificate installed and working
- [ ] Firewall configured properly
- [ ] Backup strategy implemented
- [ ] Monitoring set up
- [ ] Log rotation configured
- [ ] Performance testing completed
- [ ] Security scan performed
- [ ] Documentation updated

## 🎉 Success!

Your Enhanced Evernode host is now configured securely with:

✅ **Zero hardcoded credentials** in public files  
✅ **Secure password hashing** for admin access  
✅ **Real-time data** from your actual Evernode setup  
✅ **Professional interface** that builds tenant trust  
✅ **Role-based access** for tenants and admins  
✅ **Production-ready security** with proper permissions

**Your host is ready to accept tenants and start earning commissions!** 🚀
