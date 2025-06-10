# 🌐 Enhanced Evernode Setup

Transform any basic Evernode host into a fully-featured Docker platform with **real-time instance monitoring** and professional deployment capabilities.

## 🚀 Quick Setup (Host Operators)

### One-Command Installation

```bash
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/quick-setup.sh | sudo bash
```

**What this provides:**
- ✅ **Real-time instance monitoring** with accurate Evernode data
- ✅ **Professional landing page** at your domain/IP
- ✅ **Advanced Docker support** eliminates deployment errors
- ✅ **Smart port mapping** with `--gptcp1--5678` syntax
- ✅ **Environment variables** support `--env1--KEY-value`
- ✅ **SSL automation** capabilities
- ✅ **Container management** tools

## 📊 Live Example

See a working enhanced host: **[h20cryptonode3.dev](http://h20cryptonode3.dev/)**

This example shows:
- Real-time instance availability (3 total, 2 used, 1 available)
- Host information and lease rates
- Professional deployment examples
- Working API endpoints

## 🎯 For Developers

### Enhanced Deployment Syntax

Deploy applications with one command using enhanced hosts:

```bash
# Deploy n8n workflow automation
evdevkit acquire -i n8nio/n8n:latest--gptcp1--5678--env1--N8N_HOST-yourdomain.com rEnhancedHost -m 24

# Deploy WordPress with database
evdevkit acquire -i wordpress:latest--gptcp1--80--env1--WORDPRESS_DB_HOST-localhost rEnhancedHost -m 48

# Deploy custom app with multiple ports and variables
evdevkit acquire -i yourapp:latest--gptcp1--3000--gptcp2--8080--env1--API_KEY-secret--env2--DB_URL-connection rEnhancedHost -m 12
```

### Traditional Deployment (All Hosts)

For hosts without enhanced syntax:

```bash
# Basic deployment
evdevkit acquire -i n8nio/n8n:latest rAnyHost -m 24

# If you get "command run not found" error:
# 1. SSH to the host
# 2. Find your Sashimono user (e.g., sashi1234567890)
# 3. Redeploy manually:
sudo -u sashi1234567890 docker run -d \
  --name n8n-working \
  -p 26202:5678 \
  -e N8N_HOST="yourdomain.com" \
  -e N8N_PROTOCOL=http \
  -e N8N_SECURE_COOKIE=false \
  --restart unless-stopped \
  n8nio/n8n:latest
```

## 🛠️ Installation Guide for Host Operators

### Prerequisites

- Ubuntu 20.04/22.04 server
- Root access (sudo privileges)
- Domain name (optional but recommended)
- 2GB+ RAM, 50GB+ storage

### Step 1: Quick Installation

```bash
# Download and run the installer
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/quick-setup.sh | sudo bash
```

### Step 2: Verify Installation

After installation, your host will have:

- **Landing page**: `http://your-domain.com/` or `http://your-ip/`
- **Real-time API**: `http://your-domain.com/api/instance-count.php`
- **Management tools**: `evernode-enhanced-status`, `evernode-port-status`

### Step 3: Test the Setup

```bash
# Check system status
evernode-enhanced-status

# Test the API
curl http://localhost/api/instance-count.php

# View port mappings
evernode-port-status
```

## 📋 Enhanced Features

### Real-Time Instance Monitoring

The enhanced system provides accurate instance availability using:

- **Evernode CLI commands** (`evernode config resources`)
- **Sashimono user counting** for active instances
- **Live updates** every 30 seconds on the landing page
- **Host information** display (address, domain, reputation, lease rates)

### Advanced Port Mapping

Support for enhanced deployment syntax:

| Syntax | Description | Example |
|--------|-------------|---------|
| `--gptcp1--5678` | Map GP TCP port 1 to internal 5678 | Web applications |
| `--gptcp2--8080` | Map GP TCP port 2 to internal 8080 | Additional services |
| `--gpudp1--3000` | Map GP UDP port 1 to internal 3000 | UDP applications |
| `--env1--KEY-value` | Set environment variable | Application config |

### Docker Compatibility

Eliminates common deployment errors:

- ✅ **user_install_error** - Fixed with native Docker CLI
- ✅ **"command run not found"** - Enhanced Docker wrapper
- ✅ **Container startup issues** - Proper environment handling
- ✅ **Port mapping failures** - Automatic port allocation

## 🔧 Management Commands

After installation, these commands are available:

```bash
# System status with instance counts
evernode-enhanced-status

# Port mapping status
evernode-port-status

# Container management across all users
evernode-containers list
evernode-containers logs <container-name>
evernode-containers restart <container-name>
evernode-containers stats

# Debug tools
evernode-debug-api
```

## 📊 API Reference

### Instance Count API

**Endpoint**: `/api/instance-count.php`

**Response Example**:
```json
{
  "total": 3,
  "used": 2,
  "available": 1,
  "usage_percentage": 67,
  "status": "limited",
  "status_message": "⚡ Only 1 slot remaining",
  "last_updated": "2025-06-10 23:06:00",
  "data_source": "evernode_resources",
  "host_info": {
    "address": "rYourHostAddress",
    "domain": "yourdomain.com",
    "version": "1.0.0",
    "reputation": "255",
    "lease_amount": "0.00001 EVR/hour"
  },
  "success": true
}
```

## 🎯 Use Cases

### For Host Operators

- **Professional appearance** - Landing page shows you're serious about hosting
- **Real-time monitoring** - See exact capacity usage
- **Better developer experience** - Fewer deployment errors = happier customers
- **Competitive advantage** - Enhanced features attract more deployments

### For Developers

- **Reliable deployments** - Enhanced hosts "just work"
- **One-command setup** - Deploy complex applications instantly
- **Real capacity info** - Know if slots are available before deploying
- **Professional support** - Enhanced hosts typically provide better support

## 🔍 Troubleshooting

### Common Issues

**Landing page not accessible**:
```bash
# Check services
systemctl status nginx
systemctl status php*-fpm

# Test API directly
php /var/www/html/api/instance-count.php

# Check logs
tail -f /var/log/nginx/error.log
```

**502 Bad Gateway**:
```bash
# Run the domain fix
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/domain-fix.sh | sudo bash
```

**Instance count incorrect**:
```bash
# Debug the API
evernode-debug-api

# Check Evernode commands
evernode config resources
evernode config leaseamt
```

### Getting Help

1. **Check the example**: [h20cryptonode3.dev](http://h20cryptonode3.dev/)
2. **Run debug tools**: `evernode-enhanced-status`, `evernode-debug-api`
3. **Check logs**: `/var/log/nginx/error.log`, nginx access logs
4. **Test components**: API, landing page, Evernode commands

## 🌟 Success Stories

### Enhanced Host Example

**h20cryptonode3.dev** - Live enhanced Evernode host showing:
- ✅ Real-time monitoring (3 total, 2 used, 1 available)
- ✅ Professional landing page
- ✅ Working API endpoints
- ✅ Host information display
- ✅ Enhanced deployment examples

### Deployment Success

Enhanced hosts support one-command deployments like:
```bash
evdevkit acquire -i n8nio/n8n:latest--gptcp1--5678--env1--N8N_HOST-yourdomain.com rEnhancedHost -m 24
```

Result: Professional n8n instance accessible at `http://yourdomain.com:36525` with proper configuration.

## 📈 Benefits

### For the Evernode Ecosystem

- **Better developer experience** reduces deployment friction
- **Professional hosts** attract more serious applications
- **Real capacity data** improves resource planning
- **Enhanced features** showcase Evernode's capabilities

### For Your Business

- **Competitive advantage** over basic hosts
- **Professional appearance** builds trust
- **Reduced support requests** due to fewer deployment errors
- **Better resource utilization** with accurate monitoring

## 🚀 Quick Start Checklist

### Host Operators
- [ ] Run the one-command installer
- [ ] Verify landing page is accessible
- [ ] Test the API endpoints
- [ ] Configure domain (optional)
- [ ] Add SSL certificate (optional)

### Developers
- [ ] Find enhanced hosts with landing pages
- [ ] Test basic deployment first
- [ ] Use enhanced syntax for complex applications
- [ ] Check real-time availability before deploying

## 📞 Support

- **Documentation**: This README and inline help
- **Example Host**: [h20cryptonode3.dev](http://h20cryptonode3.dev/)
- **Debug Tools**: Built-in status and debugging commands
- **Community**: Evernode developer forums and Discord

---

## 🎉 Transform Your Host Today

```bash
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/quick-setup.sh | sudo bash
```

Join the enhanced Evernode hosting experience with professional landing pages, real-time monitoring, and developer-friendly deployment features!
