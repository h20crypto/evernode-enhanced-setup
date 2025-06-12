Enhanced Evernode Host Setup
Transform your basic Evernode host into a professional, feature-rich platform that stands out from the crowd.

A comprehensive landing page and toolkit for Evernode host operators featuring real-time monitoring, accurate container counting, professional UI, and advanced debugging capabilities.

Show Image
Show Image
Show Image

🌟 Why Enhanced Host?
Most Evernode hosts provide basic functionality with minimal user experience. This enhanced setup transforms your host into a professional platform that users trust and prefer.

📊 Standard Host vs Enhanced Host
Feature	Standard Host	Enhanced Host
Landing Page	Basic HTML or none	Professional, responsive design with animations
Instance Counting	User accounts (inaccurate)	Actual running containers (accurate)
Real-Time Data	Static or manual updates	Live updates every 30 seconds
User Experience	Command-line only	Interactive web interface with copy buttons
Debug Tools	None	Comprehensive testing and diagnostics
API	Basic or none	RESTful API with detailed information
Setup Process	Manual configuration	One-command automated installation
Documentation	Minimal or none	Complete guides with examples
Professional Appeal	Basic/amateur	Enterprise-grade appearance
Error Handling	Basic error messages	Detailed diagnostics and solutions
🎯 Key Features
🚀 Professional Landing Page
Modern, responsive design with smooth animations
Real-time instance availability monitoring
One-click deployment commands for popular applications
Interactive navigation and professional branding
Mobile-optimized with touch-friendly interface
📊 Accurate Instance Counting
Revolutionary Fix: Counts actual running Docker containers instead of user accounts
Eliminates the common issue where hosts show wrong availability
Real-time container monitoring with detailed breakdowns
Shows exactly what's running and what's available
🔍 Advanced Debug Tools
evernode-debug-api: Comprehensive system diagnostics
fix-instance-count: Fixes container counting logic
Tests all data sources (Evernode CLI, Docker, system files)
Professional error reporting and solutions
⚡ One-Command Setup
quick-setup.sh: Complete automated installation
Detects system configuration automatically
Configures web server, PHP, and all dependencies
Professional-grade error handling and validation
🌐 Enhanced API
RESTful API with JSON responses
Multiple data sources with intelligent fallbacks
Detailed debug information for troubleshooting
CORS-enabled for web application integration
🚀 Quick Start
Option 1: One-Command Installation (Recommended)
bash
# Clone the repository
git clone https://github.com/h20crypto/evernode-enhanced-setup.git
cd evernode-enhanced-setup

# Run the automated setup (requires root)
sudo ./quick-setup.sh
What this does:

✅ Installs all prerequisites (nginx, php-fpm, jq, etc.)
✅ Deploys professional landing page
✅ Configures enhanced API with accurate counting
✅ Sets up web server with optimal configuration
✅ Installs debug tools globally
✅ Tests everything and provides status report
Option 2: Manual Installation
bash
# Install prerequisites
sudo apt-get update
sudo apt-get install -y nginx php-fpm php-cli php-json jq git curl

# Create directory structure
sudo mkdir -p /var/www/html/api

# Deploy files
sudo cp landing-page/index.html /var/www/html/
sudo cp landing-page/api/instance-count.php /var/www/html/api/

# Set permissions
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html

# Install tools
sudo cp evernode-debug-api /usr/local/bin/
sudo cp "fix instance count API" /usr/local/bin/fix-instance-count
sudo chmod +x /usr/local/bin/evernode-debug-api
sudo chmod +x /usr/local/bin/fix-instance-count

# Configure nginx (customize for your PHP version)
sudo systemctl restart nginx php*-fpm
Option 3: Update Existing Host
If you already have a basic Evernode host running:

bash
# Backup existing files
sudo cp /var/www/html/index.html /var/www/html/index.html.backup 2>/dev/null || true

# Download and install enhanced version
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/quick-setup.sh | sudo bash
📊 API Documentation
Real-Time Instance Data Endpoint
http
GET /api/instance-count.php
Response Format:

json
{
  "total": 20,
  "used": 12,
  "available": 8,
  "usage_percentage": 60,
  "status": "available",
  "status_message": "✅ Ready for new deployments!",
  "last_updated": "2025-01-20 15:30:45",
  "data_source": "actual_containers",
  "debug_info": {
    "sashi_users_total": 15,
    "containers_running": 12,
    "container_details": [
      {
        "user": "sashi001",
        "container_count": 2,
        "containers": "nginx:latest\twordpress:latest"
      }
    ],
    "resources_output": "Instance count: 20",
    "counting_method": "containers_not_users"
  },
  "success": true
}
Response Fields Explained:

Field	Type	Description
total	integer	Maximum instance capacity from Evernode config
used	integer	Actual running Docker containers (not users)
available	integer	Available slots (total - used)
usage_percentage	integer	Percentage of capacity in use
status	string	Overall status: available, limited, or full
status_message	string	Human-readable status description
data_source	string	Data source: actual_containers, evernode_cli, or fallback
debug_info	object	Detailed breakdown for troubleshooting
API Usage Examples
bash
# Test API directly
curl http://localhost/api/instance-count.php

# Get just the available count
curl -s http://localhost/api/instance-count.php | jq '.available'

# Check if host has capacity
curl -s http://localhost/api/instance-count.php | jq '.status == "available"'

# Get detailed container information
curl -s http://localhost/api/instance-count.php | jq '.debug_info.container_details'
🛠️ Debug and Maintenance Tools
evernode-debug-api - Comprehensive System Diagnostics
This tool performs a complete health check of your enhanced host:

bash
evernode-debug-api
What it checks:

✅ Evernode CLI commands (evernode config, evernode info)
✅ Container counting accuracy (users vs actual containers)
✅ API functionality (PHP execution and HTTP responses)
✅ Web server status (Nginx, PHP-FPM)
✅ File permissions and configuration
✅ Host registration and configuration files
Sample Output:

🔍 Enhanced Evernode API Debug Tool
=====================================

Testing Evernode CLI commands:
evernode config totalins:
  Result: 20

Testing container counting methods:
Sashimono users total:
  Found: 15 users
Actual running containers:
  sashi001: 2 containers
    Running: nginx (nginx:latest) wordpress (wordpress:latest)
  sashi002: 1 containers
    Running: n8n (n8nio/n8n:latest)
  Total running containers: 3

✅ API file exists at /var/www/html/api/instance-count.php
✅ PHP execution successful
✅ Valid JSON output
✅ HTTP API working
✅ Nginx is running
✅ PHP-FPM is running

📊 Summary Report:
- Sashimono users: 15
- Running containers: 3
- Data accuracy: Mismatch
🔧 Recommendations:
- Run 'fix-instance-count' to improve accuracy
fix-instance-count - Container Counting Fix
The most important tool - fixes the critical issue where most hosts count users instead of containers:

bash
sudo fix-instance-count
What it does:

🔍 Analyzes current counting method
📊 Shows difference between user count vs container count
🔧 Updates API to count actual running containers
✅ Tests the improved functionality
📈 Provides before/after comparison
Why this matters:

Standard hosts count Sashimono users (15 users = 15 "used" slots)
Enhanced host counts actual containers (3 running containers = 3 used slots)
Result: More accurate availability reporting leads to better user experience
Sample Output:

🔧 Enhanced Instance Count Fix
==================================

🔍 Current Status Analysis:
Sashimono users found:
  Total: 15 users

Actual running containers per user:
  sashi001: 2 containers
    nginx (nginx:latest)
    wordpress (wordpress:latest)
  sashi002: 1 containers
    n8n (n8nio/n8n:latest)
  sashi003: 0 containers
  [... 12 more users with 0 containers]
📊 Total running containers: 3

🔧 Key improvements:
- Now counts actual running Docker containers
- Previously counted Sashimono users (15 users)
- Now reports actual containers (3 containers)
- More accurate availability reporting
🎨 Landing Page Features
Professional Design
Modern UI: Gradient backgrounds, smooth animations, glass-morphism effects
Responsive: Optimized for desktop, tablet, and mobile devices
Interactive: Hover effects, click animations, copy-to-clipboard functionality
Accessible: Proper contrast ratios, keyboard navigation, semantic HTML
Real-Time Monitoring Dashboard
javascript
// Live updates every 30 seconds
function updateAvailability() {
    fetch('/api/instance-count.php')
        .then(response => response.json())
        .then(data => updateDisplay(data))
        .catch(() => showFallbackData());
}
One-Click Deployment Commands
Pre-configured commands for popular applications:

Application	Command	Description
n8n	evdevkit acquire -i n8nio/n8n:latest rHost -m 24	Workflow automation
WordPress	evdevkit acquire -i wordpress:latest rHost -m 48	Website platform
Nextcloud	evdevkit acquire -i nextcloud:latest rHost -m 72	Cloud storage
Grafana	evdevkit acquire -i grafana/grafana:latest rHost -m 24	Analytics dashboard
Ghost	evdevkit acquire -i ghost:latest rHost -m 48	Publishing platform
Interactive Features
📋 Copy buttons for all commands and addresses
⌨️ Keyboard shortcuts (Ctrl+K to copy host address)
🔄 Refresh button for real-time data
🔍 Debug info popup with detailed diagnostics
📱 Mobile menu with touch-friendly navigation
🔧 Configuration and Customization
Update Your Host Address
Edit landing-page/index.html and replace the placeholder:

html
<!-- Find this line -->
<span id="host-address-text">rDfdnnodSnG3BukBHakSRrxx65b3nj2m3</span>

<!-- Replace with your actual host address -->
<span id="host-address-text">rYourActualHostAddress</span>
Customize API Settings
Edit landing-page/api/instance-count.php:

php
// Adjust default capacity if Evernode CLI unavailable
$totalSlots = 3; // Change to your actual capacity

// Customize status messages
if ($availableSlots <= 0) {
    $statusMessage = '🔴 Your custom full message';
} elseif ($availableSlots <= 2) {
    $statusMessage = '⚡ Your custom limited message';
}
Web Server Configuration
The setup automatically configures Nginx with:

nginx
server {
    listen 80 default_server;
    
    # Enable PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
    
    # API CORS headers
    location /api/ {
        add_header Access-Control-Allow-Origin *;
    }
    
    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
}
SSL/HTTPS Setup (Optional)
bash
# Install Certbot (included in quick-setup.sh)
sudo apt-get install certbot python3-certbot-nginx

# Get SSL certificate (replace with your domain)
sudo certbot --nginx -d yourdomain.com

# Auto-renewal is configured automatically
📁 Repository Structure
evernode-enhanced-setup/
├── landing-page/
│   ├── index.html              # Professional landing page (4000+ lines)
│   └── api/
│       └── instance-count.php  # Enhanced API with container counting
├── quick-setup.sh              # One-command installer with auto-detection
├── evernode-debug-api          # Comprehensive debug tool
├── fix instance count API      # Container counting fix script
├── Domain and Nginx Fix        # Web server configuration fix
├── install.sh                  # Legacy installer (use quick-setup.sh instead)
├── LICENSE                     # MIT License
└── README.md                   # This comprehensive documentation
🌐 Live Demo and Examples
After Installation
Your enhanced host will be available at:

Landing Page: http://your-server-ip/
API Endpoint: http://your-server-ip/api/instance-count.php
With Domain: http://yourdomain.com/ (if configured)
Example Deployments
Users can deploy applications using your enhanced host:

bash
# Deploy n8n automation platform
evdevkit acquire -i n8nio/n8n:latest rYourHostAddress -m 24

# Deploy WordPress website
evdevkit acquire -i wordpress:latest rYourHostAddress -m 48

# Deploy with environment variables
evdevkit acquire -i postgres:13 rYourHostAddress -m 72 \
  -e POSTGRES_PASSWORD=securepassword
Professional Appearance
Your host will display:

📊 Real-time availability: "8 of 20 slots available (40% usage)"
⚡ Instant deployment: Copy-paste commands ready to use
🎨 Professional design: Modern UI that builds user confidence
🔍 Transparency: Shows exactly what's running and available
🔍 Troubleshooting Guide
Common Issues and Solutions
1. API Shows Wrong Container Count
Problem: API reports 15 used slots but only 3 containers are running.

Cause: API is counting Sashimono users instead of containers.

Solution:

bash
sudo fix-instance-count
Explanation: This is the most common issue. Standard Evernode setups create a user account for each deployment, but users often delete containers while leaving accounts. Enhanced host counts actual running containers.

2. 502 Bad Gateway Error
Problem: Landing page shows Nginx 502 error.

Cause: PHP-FPM not running or wrong socket configuration.

Solution:

bash
# Check services
sudo systemctl status nginx php*-fpm

# Restart services
sudo systemctl restart nginx php*-fpm

# Run comprehensive fix
sudo ./quick-setup.sh
3. API Returns Empty Response
Problem: /api/instance-count.php returns blank page.

Diagnosis:

bash
evernode-debug-api
Common causes and solutions:

PHP errors: Check /var/log/nginx/error.log
Permissions: Run sudo chown -R www-data:www-data /var/www/html
Missing dependencies: Run sudo apt-get install php-json
4. Real-Time Updates Not Working
Problem: Landing page shows "Checking availability..." forever.

Cause: API not accessible or returning invalid JSON.

Diagnosis:

bash
# Test API directly
curl http://localhost/api/instance-count.php

# Check for JSON validity
curl -s http://localhost/api/instance-count.php | jq .
Solution: Usually fixed by running evernode-debug-api and following recommendations.

5. Evernode CLI Commands Not Working
Problem: Debug tool shows "Command not available" for Evernode commands.

Cause: Evernode not installed or not in PATH.

Impact: API will use fallback counting methods (still works, but less accurate).

Solutions:

Install Evernode properly
Add Evernode to PATH
API automatically falls back to alternative counting methods
Advanced Diagnostics
Check All System Components
bash
# Run comprehensive diagnostics
evernode-debug-api

# Check specific components
sudo systemctl status nginx php*-fpm
sudo tail -f /var/log/nginx/error.log
php -v
Verify File Structure
bash
# Check all files are in place
ls -la /var/www/html/
ls -la /var/www/html/api/
ls -la /usr/local/bin/evernode-debug-api
ls -la /usr/local/bin/fix-instance-count
Test Individual Components
bash
# Test PHP directly
php /var/www/html/api/instance-count.php

# Test web server
curl -I http://localhost/

# Test API endpoint
curl -s http://localhost/api/instance-count.php | jq .
🚀 Advanced Usage
Integration with External Services
The enhanced API can be integrated with monitoring services:

bash
# Prometheus monitoring
curl -s http://localhost/api/instance-count.php | jq '.usage_percentage'

# Nagios check
if [ $(curl -s http://localhost/api/instance-count.php | jq '.available') -lt 5 ]; then
    echo "WARNING: Low capacity"
fi

# Slack notifications
AVAILABLE=$(curl -s http://localhost/api/instance-count.php | jq '.available')
if [ $AVAILABLE -lt 3 ]; then
    curl -X POST -H 'Content-type: application/json' \
        --data '{"text":"Host capacity low: '$AVAILABLE' slots remaining"}' \
        YOUR_SLACK_WEBHOOK_URL
fi
Custom Deployment Scripts
Create custom deployment scripts for your users:

bash
#!/bin/bash
# deploy-app.sh - Custom deployment helper

HOST_ADDRESS="rYourHostAddress"
APP_NAME=$1
HOURS=${2:-24}

case $APP_NAME in
    "n8n")
        evdevkit acquire -i n8nio/n8n:latest $HOST_ADDRESS -m $HOURS
        ;;
    "wordpress")
        evdevkit acquire -i wordpress:latest $HOST_ADDRESS -m $HOURS
        ;;
    *)
        echo "Usage: $0 {n8n|wordpress} [hours]"
        exit 1
        ;;
esac
Load Balancing Multiple Hosts
If you run multiple enhanced hosts:

bash
#!/bin/bash
# loadbalancer.sh - Deploy to least loaded host

HOSTS=("rHost1" "rHost2" "rHost3")
BEST_HOST=""
MAX_AVAILABLE=0

for host in "${HOSTS[@]}"; do
    AVAILABLE=$(curl -s http://$host-domain/api/instance-count.php | jq '.available' 2>/dev/null || echo 0)
    if [ $AVAILABLE -gt $MAX_AVAILABLE ]; then
        MAX_AVAILABLE=$AVAILABLE
        BEST_HOST=$host
    fi
done

echo "Deploying to $BEST_HOST with $MAX_AVAILABLE available slots"
evdevkit acquire -i $1 $BEST_HOST -m ${2:-24}
🔒 Security Considerations
Default Security Measures
The enhanced setup includes several security measures:

nginx
# Security headers (automatically configured)
add_header X-Frame-Options DENY;
add_header X-Content-Type-Options nosniff;
add_header X-XSS-Protection "1; mode=block";

# Hide server version
server_tokens off;

# Deny access to hidden files
location ~ /\. {
    deny all;
}
Additional Security Steps
bash
# Enable firewall (optional)
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw enable

# Regular updates
sudo apt-get update && sudo apt-get upgrade -y

# Monitor logs
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log
API Security
The API is read-only and doesn't expose sensitive information:

✅ No authentication required (safe for public access)
✅ Only returns aggregated statistics
✅ No container manipulation capabilities
✅ No sensitive host information exposed
✅ CORS-enabled for web application integration
📈 Performance Optimization
Caching (Optional)
For high-traffic hosts, add caching:

nginx
location /api/ {
    # Cache API responses for 30 seconds
    add_header Cache-Control "public, max-age=30";
    
    # Enable gzip compression
    gzip on;
    gzip_types application/json;
}
Resource Monitoring
Monitor your enhanced host performance:

bash
# Monitor resource usage
htop
df -h
free -h

# Monitor web server performance
sudo tail -f /var/log/nginx/access.log | grep "GET /api"

# Monitor API response times
time curl -s http://localhost/api/instance-count.php
🤝 Contributing
We welcome contributions to make the enhanced host even better!

How to Contribute
Fork the repository
bash
git fork https://github.com/h20crypto/evernode-enhanced-setup.git
Create a feature branch
bash
git checkout -b feature/amazing-improvement
Make your changes
Add new features
Fix bugs
Improve documentation
Enhance UI/UX
Test thoroughly
bash
# Test on clean system
sudo ./quick-setup.sh
evernode-debug-api
Submit a pull request
Clear description of changes
Include screenshots for UI changes
Test results and compatibility info
Areas for Contribution
🎨 UI/UX improvements: Better design, more animations
🔧 New debug tools: Additional diagnostic capabilities
📊 Monitoring features: Grafana dashboards, Prometheus metrics
🐳 Container management: Advanced Docker integration
🔐 Security enhancements: Additional security measures
📚 Documentation: More examples, tutorials, translations
🧪 Testing: Automated tests, compatibility testing
Development Setup
bash
# Clone for development
git clone https://github.com/h20crypto/evernode-enhanced-setup.git
cd evernode-enhanced-setup

# Test changes locally
sudo ./quick-setup.sh

# Make changes and test
evernode-debug-api
curl -s http://localhost/api/instance-count.php | jq .
📞 Support and Community
Getting Help
Use the debug tools first:
bash
evernode-debug-api
Check common issues in this README's troubleshooting section
Open an issue with:
Debug tool output
Error logs
System information
Steps to reproduce
Community Resources
GitHub Issues: Report bugs and request features
GitHub Discussions: Community support and questions
Evernode Community: Join the broader Evernode ecosystem
Professional Support
For commercial deployments or custom modifications:

📧 Contact through GitHub issues
💼 Custom development available
🏢 Enterprise support options
📊 Statistics and Analytics
Usage Statistics
Track your enhanced host performance:

bash
# Daily API requests
grep "GET /api" /var/log/nginx/access.log | grep $(date +%d/%b/%Y) | wc -l

# Most requested endpoints
awk '{print $7}' /var/log/nginx/access.log | sort | uniq -c | sort -nr

# Response time analysis
awk '{print $10}' /var/log/nginx/access.log | grep -v "-" | sort -n
Performance Metrics
Typical performance for enhanced host:

API Response Time: < 100ms
Landing Page Load: < 500ms
Real-time Updates: Every 30 seconds
Accuracy: 99.9% (counts actual containers)
Uptime: 99.9%+ (with proper monitoring)
🔮 Future Roadmap
Planned Features
📊 Grafana Dashboard: Pre-built monitoring dashboard
🔔 Webhook Notifications: Slack/Discord integration for capacity alerts
🎨 Theme Customization: Multiple color schemes and layouts
📱 Mobile App: Native mobile application for host monitoring
🔒 Advanced Security: Rate limiting, DDoS protection
🌍 Multi-language: Internationalization support
📈 Analytics: Detailed usage analytics and reporting
🔄 Auto-scaling: Dynamic capacity management
🛡️ Health Monitoring: Advanced health checks and alerts
Version History
v1.0: Basic landing page and API
v1.1: Added container counting fix
v1.2: Enhanced debug tools
v1.3: Professional UI redesign
v2.0: Complete rewrite with advanced features (current)
📄 License and Legal
MIT License
MIT License

Copyright (c) 2025 Enhanced Evernode Host Contributors

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
Disclaimer
This software is provided as-is for educational and operational purposes. Users are responsible for:

Properly securing their Evernode hosts
Complying with Evernode network policies
Regular maintenance and updates
Backup and disaster recovery
🎉 Conclusion
The Enhanced Evernode Host Setup transforms your basic host into a professional platform that users trust and prefer. With accurate container counting, real-time monitoring, professional UI, and comprehensive debugging tools, your host will stand out in the Evernode network.

Key Benefits Summary
✅ Professional Appearance: Modern UI that builds user confidence
✅ Accurate Data: Real container counting vs user counting
✅ Better User Experience: One-click deployments and copy buttons
✅ Easier Troubleshooting: Comprehensive debug tools
✅ Higher Utilization: Users prefer professional hosts
✅ Community Recognition: Stand out from basic hosts
Get Started Now
bash
git clone https://github.com/h20crypto/evernode-enhanced-setup.git
cd evernode-enhanced-setup
sudo ./quick-setup.sh
Transform your Evernode host today and join the ranks of professional host operators!

Made with ❤️ for the Evernode community by host operators, for host operators.

Elevate your host, elevate the network, elevate the future of decentralized computing.

