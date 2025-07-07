# Enhanced Evernode Troubleshooting

## 502 Bad Gateway Errors
If you see 502 errors:
1. Check PHP-FPM is running: `sudo systemctl status php8.1-fpm`
2. Fix nginx config: `sudo sed -i 's/php8.3-fpm.sock/php8.1-fpm.sock/g' /etc/nginx/sites-available/default`
3. Restart services: `sudo systemctl restart php8.1-fpm nginx`

## Enhanced Detection Issues
If seeing too many "Enhanced" hosts (100+):
- Update enhanced-search.php to use real beacon detection
- Should show only 3-5 actual Enhanced hosts

## API Test Commands
- Test API: `curl "http://localhost/api/enhanced-search.php?action=test"`
- Test Enhanced count: `curl "http://localhost/api/enhanced-search.php?action=search&enhanced_only=true"`
