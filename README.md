# Enhanced Evernode Host Setup v2.0

Transform your basic Evernode host into a **professional, feature-rich platform** that stands out from the crowd with modern UI, real-time monitoring, and enterprise-grade tools.

A comprehensive landing page and toolkit for Evernode host operators featuring **glassmorphism design**, **real-time container monitoring**, **accurate container counting**, **professional debugging capabilities**, and **mobile-responsive interface**.

> **⭐ NEW in v2.0:** Complete UI overhaul with modern glassmorphism design, enhanced real-time monitoring, professional animations, and comprehensive management tools.

## 🌟 Why Enhanced Host v2.0?

Most Evernode hosts provide basic functionality with minimal user experience. This enhanced setup transforms your host into a **professional platform** that users trust and prefer, now with cutting-edge UI and advanced monitoring capabilities.

### 📊 Standard Host vs Enhanced Host v2.0

| Feature | Standard Host | Enhanced Host v1.x | **Enhanced Host v2.0** |
|---------|---------------|---------------------|------------------------|
| **Landing Page** | Basic HTML or none | Professional design | **Modern glassmorphism UI with animations** |
| **Real-Time Updates** | Static or manual | Basic updates | **Live monitoring every 30 seconds** |
| **Instance Counting** | User accounts (inaccurate) | Container counting | **Advanced Docker API integration** |
| **User Interface** | Command-line only | Basic web interface | **Interactive web app with smooth animations** |
| **Mobile Support** | None | Limited | **Fully responsive with touch optimization** |
| **Debug Tools** | None | Basic diagnostics | **Comprehensive testing and monitoring suite** |
| **API** | Basic or none | RESTful API | **Enhanced API with detailed debug info** |
| **Visual Design** | Amateur appearance | Professional look | **Enterprise-grade UI with modern aesthetics** |
| **Animations** | None | Static | **Smooth animations and hover effects** |
| **Performance** | Basic | Good | **Optimized with caching and compression** |
| **Security** | Minimal | Standard headers | **Enhanced security with CORS and headers** |

## 🎯 Key Features v2.0

### 🚀 **Professional Landing Page with Modern UI**
- **Glassmorphism Design**: Modern transparent elements with backdrop blur effects
- **Smooth Animations**: Professional fade-in, hover, and transition effects
- **Real-time Instance Monitoring**: Live availability updates every 30 seconds
- **One-click Deployment Commands**: Copy-paste ready commands for popular applications
- **Interactive Navigation**: Smooth scrolling with animated navbar
- **Mobile-First Design**: Touch-optimized interface for all devices
- **Hidden Debug Mode**: Click availability card 5 times to unlock advanced debugging

### 📊 **Advanced Real-Time Monitoring**
- **Revolutionary Fix**: Counts actual running Docker containers instead of user accounts
- **Live Dashboard**: Real-time container monitoring with visual progress bars
- **Intelligent Fallbacks**: Multiple data sources ensure accuracy
- **Status Indicators**: Color-coded availability with dynamic messages
- **Performance Metrics**: Shows exactly what's running and what's available
- **Auto-refresh**: Updates every 30 seconds without page reload

### 🔍 **Professional Debug Tools Suite**
- **`evernode-debug-api`**: Comprehensive system diagnostics with detailed reporting
- **`fix-instance-count`**: Advanced container counting logic fixes
- **`evernode-monitor`**: Real-time system and API monitoring
- **`fix-domain-nginx`**: Automatic domain and web server configuration
- **Professional Error Reporting**: Detailed diagnostics with actionable solutions

### ⚡ **Enhanced One-Command Setup**
- **`quick-setup.sh`**: Complete automated installation with intelligent detection
- **Auto-configuration**: Detects PHP version, IP addresses, and system specs
- **Professional Output**: Beautiful colored terminal output with status indicators
- **Comprehensive Testing**: Validates all components after installation
- **Error Recovery**: Intelligent error handling with detailed reporting

### 🌐 **Advanced API v2.0**
- **RESTful API**: Enhanced JSON responses with comprehensive data
- **Multiple Data Sources**: Evernode CLI, Docker API, and system file integration
- **Debug Information**: Detailed breakdown for professional troubleshooting
- **CORS-Enabled**: Full web application integration support
- **Performance Optimized**: Fast response times with intelligent caching

## 🚀 Quick Start v2.0

### Option 1: **One-Command Installation** (Recommended)
```bash
# Clone the repository
git clone https://github.com/h20crypto/evernode-enhanced-setup.git
cd evernode-enhanced-setup

# Run the enhanced automated setup (requires root)
sudo ./quick-setup.sh
```

**What this does:**

✅ **Installs all prerequisites** (nginx, php-fpm, jq, etc.)  
✅ **Deploys modern glassmorphism landing page**  
✅ **Configures enhanced API with real-time monitoring**  
✅ **Sets up optimized web server configuration**  
✅ **Installs professional debug and monitoring tools**  
✅ **Configures security headers and performance optimization**  
✅ **Tests everything and provides comprehensive status report**  

### Option 2: **Direct Download Installation**
```bash
# One-command installation from GitHub
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/quick-setup.sh | sudo bash
```

### Option 3: **Manual Installation**
```bash
# Install prerequisites
sudo apt-get update
sudo apt-get install -y nginx php-fpm php-cli php-json jq git curl

# Create enhanced directory structure
sudo mkdir -p /var/www/html/api

# Deploy enhanced files
sudo curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/landing-page/index.html -o /var/www/html/index.html
sudo curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/landing-page/api/instance-count.php -o /var/www/html/api/instance-count.php

# Set enhanced permissions
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 644 /var/www/html
sudo chmod 755 /var/www/html /var/www/html/api

# Install enhanced tools
sudo curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/evernode-debug-api -o /usr/local/bin/evernode-debug-api
sudo curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/Domain%20and%20Nginx%20Fix -o /usr/local/bin/fix-domain-nginx
sudo chmod +x /usr/local/bin/evernode-debug-api /usr/local/bin/fix-domain-nginx

# Configure and restart services
sudo systemctl restart nginx php*-fpm
```

## 📊 Enhanced API Documentation v2.0

### **Real-Time Instance Data Endpoint**
```http
GET /api/instance-count.php
```

### **Enhanced Response Format v2.0:**
```json
{
  "total": 50,
  "used": 15,
  "available": 35,
  "usage_percentage": 30,
  "status": "available",
  "status_message": "✅ Ready for new deployments!",
  "last_updated": "2025-01-20 15:30:45",
  "data_source": "actual_containers",
  "host_info": {
    "address": "rYourHostAddress123",
    "domain": "host.example.com",
    "version": "0.12.0",
    "reputation": "255/255",
    "lease_amount": "0.00001 EVR/hour"
  },
  "debug_info": {
    "sashi_users_total": 20,
    "containers_running": 15,
    "container_details": [
      {
        "user": "sashi001",
        "container_count": 2,
        "containers": "nginx_app (nginx:latest)\twordpress_site (wordpress:latest)"
      }
    ],
    "resources_output": "Instance count: 50",
    "lease_output": "0.00001 EVRs",
    "counting_method": "advanced_docker_api"
  },
  "success": true
}
```

### **New Response Fields in v2.0:**

| Field | Type | Description |
|-------|------|-------------|
| `host_info` | object | **NEW:** Complete host information from Evernode CLI |
| `container_details` | array | **ENHANCED:** Detailed per-user container breakdown |
| `counting_method` | string | **NEW:** Method used for accurate counting |
| `lease_amount` | string | **NEW:** Current lease pricing information |
| `reputation` | string | **NEW:** Host reputation score |

## 🛠️ Enhanced Debug and Maintenance Tools v2.0

### **`evernode-debug-api`** - Professional System Diagnostics
Comprehensive health check with enhanced reporting:

```bash
evernode-debug-api
```

**Enhanced v2.0 features:**
- ✅ **Complete Evernode CLI testing** (totalins, activeins, info, leaseamt)
- ✅ **Advanced container analysis** (per-user breakdown with image details)
- ✅ **API functionality validation** (PHP execution, HTTP responses, JSON validity)
- ✅ **Service health monitoring** (Nginx, PHP-FPM status and performance)
- ✅ **Configuration validation** (file permissions, host registration)
- ✅ **Professional recommendations** (actionable solutions for issues)

### **`evernode-monitor`** - Real-Time System Monitoring (NEW)
Professional monitoring dashboard:

```bash
evernode-monitor
```

**Features:**
- 📊 **System Resources**: CPU, memory, disk usage with percentages
- 🔧 **Service Status**: Real-time Nginx and PHP-FPM monitoring  
- 📡 **API Health**: Live API response testing with detailed metrics
- 📈 **Instance Statistics**: Current capacity and utilization

### **`fix-domain-nginx`** - Advanced Configuration Fix (NEW)
Automatic domain and web server configuration:

```bash
sudo fix-domain-nginx
```

**Enhanced features:**
- 🔍 **Auto PHP-FPM Detection**: Finds correct PHP version and socket
- 🌐 **IP Address Detection**: Configures IPv4 and IPv6 automatically  
- 🔧 **Professional Nginx Config**: Optimized settings with security headers
- 🧪 **Comprehensive Testing**: Validates PHP, API, and external access
- 📊 **DNS Validation**: Checks domain configuration and provides recommendations

## 🎨 Enhanced Landing Page Features v2.0

### **Modern UI Design**
- **Glassmorphism Effects**: Transparent elements with backdrop blur and subtle borders
- **Gradient Backgrounds**: Professional color schemes with smooth transitions  
- **Smooth Animations**: Fade-in effects, hover animations, and loading states
- **Professional Typography**: Modern font stack with proper spacing and hierarchy
- **Interactive Elements**: Hover effects, click animations, and visual feedback

### **Real-Time Monitoring Dashboard**
```javascript
// Enhanced live updates every 30 seconds with fallback handling
function updateAvailability() {
    fetch('/api/instance-count.php')
        .then(response => response.json())
        .then(data => updateDisplay(data))
        .catch(error => {
            console.log('Using fallback data:', error);
            const mockData = generateMockData();
            updateDisplay(mockData);
        });
    
    setTimeout(updateAvailability, 30000);
}
```

### **Enhanced One-Click Deployment Commands**
Pre-configured commands with enhanced copy functionality:

| Application | Command | Enhanced Features |
|-------------|---------|-------------------|
| **n8n** | `evdevkit acquire -i n8nio/n8n:latest rHost -m 24` | ✅ Workflow automation with instant copy |
| **WordPress** | `evdevkit acquire -i wordpress:latest rHost -m 48` | ✅ Complete website platform setup |
| **Nextcloud** | `evdevkit acquire -i nextcloud:latest rHost -m 72` | ✅ Personal cloud with file sharing |
| **Grafana** | `evdevkit acquire -i grafana/grafana:latest rHost -m 24` | ✅ Analytics dashboard with login info |
| **Ghost** | `evdevkit acquire -i ghost:latest rHost -m 48` | ✅ Modern publishing platform |

### **Enhanced Interactive Features**
- 📋 **Smart Copy Buttons**: One-click copy for all commands and addresses
- ⌨️ **Keyboard Shortcuts**: `Ctrl+K` to copy host address, `Ctrl+Shift+R` to refresh
- 🔄 **Intelligent Refresh**: Real-time data updates with loading animations
- 🔍 **Hidden Debug Mode**: Click availability card 5 times to unlock debug panel
- 📱 **Touch Optimization**: Mobile-friendly interactions with proper touch targets
- 🎯 **Visual Feedback**: Loading states, success notifications, and error handling

## 🔧 Enhanced Configuration and Customization v2.0

### **Auto-Configuration Features**
The enhanced setup automatically:
- 🔍 **Detects PHP Version**: Finds and configures correct PHP-FPM socket
- 🌐 **Configures IP Addresses**: Sets up IPv4 and IPv6 access
- 🏷️ **Updates Host Information**: Replaces placeholder host addresses
- 🔒 **Applies Security Headers**: Configures professional security settings
- ⚡ **Optimizes Performance**: Sets up caching and compression

### **Enhanced Nginx Configuration**
```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    
    # Multi-domain support with auto-detection
    server_name yourdomain.com 192.168.1.100 2001:db8::1 localhost _;
    
    # Enhanced security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    
    # API CORS configuration with preflight handling
    location /api/ {
        add_header Access-Control-Allow-Origin *;
        add_header Access-Control-Allow-Methods "GET, POST, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type, Authorization";
        
        if ($request_method = 'OPTIONS') {
            return 204;
        }
    }
    
    # Performance optimization
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

## 🔍 Enhanced Troubleshooting Guide v2.0

### **Enhanced Diagnostic Commands**
```bash
# Professional system diagnostics
evernode-debug-api

# Real-time monitoring
evernode-monitor

# Fix configuration issues
sudo fix-domain-nginx

# Test API responses
curl -s http://localhost/api/instance-count.php | jq .
```

### **Common Issues and Enhanced Solutions**

#### **1. Enhanced Container Counting Fix**
**Problem**: API reports incorrect container count  
**Enhanced Solution**:
```bash
sudo fix-instance-count
# Now with advanced Docker API integration and detailed reporting
```

#### **2. Modern UI Not Loading**
**Problem**: Landing page shows basic HTML instead of glassmorphism UI  
**Enhanced Diagnosis**:
```bash
# Check if enhanced version is installed
curl -s http://localhost/ | grep "Enhanced Evernode Host"
curl -s http://localhost/ | grep "glassmorphism"

# Reinstall enhanced version
sudo ./quick-setup.sh
```

#### **3. Real-Time Updates Not Working**
**Problem**: Dashboard shows static data instead of live updates  
**Enhanced Solution**:
```bash
# Test API endpoint
curl -s http://localhost/api/instance-count.php | jq .success

# Check browser console for JavaScript errors
# Test with different browsers

# Verify CORS headers
curl -I http://localhost/api/instance-count.php
```

## 📈 Enhanced Performance and Security v2.0

### **Performance Optimizations**
```bash
# Enhanced caching configuration
location /api/ {
    add_header Cache-Control "public, max-age=30";
    gzip on;
    gzip_types application/json;
}

# Static file optimization
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
    expires 30d;
    add_header Cache-Control "public, immutable";
    access_log off;
}
```

### **Enhanced Security Measures**
- 🔒 **Security Headers**: X-Frame-Options, X-Content-Type-Options, XSS Protection
- 🛡️ **CORS Configuration**: Proper cross-origin resource sharing setup
- 🔐 **Access Controls**: Hidden file protection and sensitive directory blocking
- 🚫 **Server Token Hiding**: Removes server version information
- 📊 **Request Validation**: Input sanitization and validation

## 🚀 Enhanced Future Roadmap v2.1+

### **Planned Enhancements**
- 📊 **Grafana Integration**: Pre-built monitoring dashboards
- 🔔 **Webhook Notifications**: Slack/Discord integration for capacity alerts  
- 🎨 **Theme System**: Multiple color schemes and customizable layouts
- 📱 **Progressive Web App**: Installable mobile application
- 🔒 **Advanced Security**: Rate limiting, DDoS protection, and WAF
- 🌍 **Internationalization**: Multi-language support
- 📈 **Analytics Dashboard**: Detailed usage analytics and reporting
- 🔄 **Auto-scaling**: Dynamic capacity management
- 🛡️ **Health Monitoring**: Advanced health checks with alerting
- 🎯 **Load Balancing**: Multi-host deployment optimization

### **Version History**
- **v1.0**: Basic landing page and API
- **v1.1**: Added container counting fix  
- **v1.2**: Enhanced debug tools
- **v1.3**: Professional UI redesign
- **v2.0**: 🚀 **Complete rewrite with glassmorphism UI, real-time monitoring, and professional tools** (current)

## 📁 Enhanced Repository Structure v2.0

```
evernode-enhanced-setup/
├── landing-page/
│   ├── index.html              # 🌟 Modern glassmorphism landing page (5000+ lines)
│   └── api/
│       └── instance-count.php  # 📊 Enhanced API with real-time monitoring
├── quick-setup.sh              # ⚡ Professional one-command installer v2.0
├── install.sh                  # 🔧 Enhanced installer with auto-detection
├── evernode-debug-api          # 🔍 Comprehensive diagnostic suite
├── Domain and Nginx Fix        # 🌐 Advanced domain and web server fix
├── fix instance count API      # 📈 Container counting logic improvements
├── LICENSE                     # MIT License
└── README.md                   # 📚 This comprehensive documentation
```

## 🔍 Host Discovery & Cluster Management

### For Tenants - Find Enhanced Hosts

```bash
# Simple CLI discovery
node tools/discover-cli.js

# Filter by location and availability  
node tools/discover-cli.js --location us --available-only

# Creates cluster_hosts.txt for evdevkit
evdevkit cluster-create /path/to/contract cluster_hosts.txt -m 24

## 🌐 Enhanced Live Demo v2.0

### **After Enhanced Installation**
Your professional host will be available at:

- **Landing Page**: `http://your-server-ip/` (with glassmorphism UI)
- **API Endpoint**: `http://your-server-ip/api/instance-count.php` (enhanced data)
- **With Domain**: `http://yourdomain.com/` (auto-configured)

### **Enhanced Professional Appearance**
Your host will display:

- 📊 **Real-time availability**: "35 of 50 slots available (30% usage)" with animated progress bar
- ⚡ **Instant deployment**: Copy-paste commands with smooth animations  
- 🎨 **Modern design**: Glassmorphism UI that builds user confidence
- 🔍 **Complete transparency**: Shows exactly what's running with container details
- 📱 **Mobile optimization**: Perfect experience on all devices
- 🚀 **Professional features**: Hidden debug mode, keyboard shortcuts, live updates

## 🎉 Enhanced Conclusion v2.0

The **Enhanced Evernode Host Setup v2.0** transforms your basic host into a **professional, enterprise-grade platform** that users trust and prefer. With **modern glassmorphism UI**, **real-time monitoring**, **accurate container counting**, **professional debugging tools**, and **mobile optimization**, your host will stand out in the Evernode network.

### **Enhanced Benefits Summary v2.0**
✅ **Modern Professional Appearance**: Glassmorphism UI that builds user confidence  
✅ **Real-Time Monitoring**: Live updates with accurate container counting  
✅ **Superior User Experience**: One-click deployments with smooth animations  
✅ **Professional Tools**: Comprehensive debug and monitoring suite  
✅ **Mobile Optimization**: Perfect experience on all devices  
✅ **Enhanced Security**: Professional-grade security headers and configurations  
✅ **Performance Optimization**: Fast loading with intelligent caching  
✅ **Community Recognition**: Stand out as a premium host operator  

### **Get Started with Enhanced v2.0 Now**
```bash
git clone https://github.com/h20crypto/evernode-enhanced-setup.git
cd evernode-enhanced-setup
sudo ./quick-setup.sh
```
## 🚀 Premium: Cluster Management

Deploy applications across multiple hosts with our advanced cluster management system.

**One-time purchase: $49.99**
- Create unlimited clusters
- Real-time monitoring and management  
- Advanced deployment features
- Priority technical support

[Purchase License](https://yourhost.com/purchase-license.html) | [Activate License](https://yourhost.com/activate-cluster.html)
**Transform your Evernode host today and join the ranks of professional host operators with cutting-edge technology!**
## 💎 Premium: Cluster Manager ($49.99)

### ⚡ Revolutionary Features
- **One-Click Magic**: Extend all cluster instances in 30 seconds vs 30+ minutes manually
- **2400% faster** than SSH-ing into each host individually  
- **Zero downtime risk** - Never miss an instance again
- **NFT Licenses**: First software licenses as NFTs on Xahau Network

### 🛒 Get Started
- [Calculate Your Savings](https://yourhost.com/cluster/roi-calculator.html)
- [Purchase License ($49.99)](https://yourhost.com/cluster/paywall.html)  
- [Manage Clusters](https://yourhost.com/cluster/dashboard.html)

### 🔍 For Developers
```bash
# Find enhanced hosts
node tools/discover-cli.js --available-only

# Deploy clusters  
evdevkit cluster-create /path/to/contract cluster_hosts.txt -m 24

---

## 📄 License

MIT License - See [LICENSE](LICENSE) file for details.

## 🤝 Contributing

We welcome contributions! Fork the repository, make your changes, and submit a pull request. See our contributing guidelines for more details. 
Or buy me a beer! coff.ee/h20crypto14

---

**Made with ❤️ for the Evernode community by host operators, for host operators.**

**🚀 Elevate your host, elevate the network, elevate the future of decentralized computing.**

> **Enhanced Evernode Host v2.0** - Where professional hosting meets cutting-edge technology.
