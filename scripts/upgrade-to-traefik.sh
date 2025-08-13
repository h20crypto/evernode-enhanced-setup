#!/bin/bash

# Enhanced Evernode - Traefik Professional Upgrade
# Repository: https://github.com/h20crypto/evernode-enhanced-setup
# Version: 1.0.0

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Configuration
REPO_BASE="https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main"
TRAEFIK_DIR="/opt/evernode-traefik"
BACKUP_DIR="/opt/evernode-backup-$(date +%Y%m%d-%H%M%S)"
DOMAIN="${DOMAIN:-$(hostname -f)}"
EMAIL="${EMAIL:-admin@$DOMAIN}"
AUTO_INSTALL="${1:-}"

# Print functions
print_header() {
    echo -e "${PURPLE}"
    echo "=========================================="
    echo "   🚀 Enhanced Evernode → Traefik"
    echo "=========================================="
    echo -e "${NC}"
    echo "Transform your host from basic ports to professional domains"
    echo ""
    echo -e "${RED}BEFORE:${NC} tenant1.yourhost.com:26201 (ugly, technical)"
    echo -e "${GREEN}AFTER:${NC}  https://myapp.yourhost.com (professional, branded)"
    echo ""
    echo "✅ Automatic SSL certificates"
    echo "✅ Custom tenant domains"  
    echo "✅ GitHub integration (push to deploy)"
    echo "✅ Zero-downtime updates"
    echo "✅ Professional appeal = more tenants"
    echo ""
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_feature() {
    echo -e "${PURPLE}🌟 $1${NC}"
}

# Check if this is an Enhanced Evernode host
check_enhanced_host() {
    if [[ ! -f "/var/www/html/index.html" ]] || ! grep -q "Enhanced Evernode" "/var/www/html/index.html" 2>/dev/null; then
        print_error "This doesn't appear to be an Enhanced Evernode host"
        echo ""
        echo "Please install Enhanced Evernode first:"
        echo "curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/scripts/quick-setup.sh | sudo bash"
        exit 1
    fi
    print_success "Enhanced Evernode host detected"
}

# Check system requirements
check_requirements() {
    print_info "Checking system requirements..."
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        print_info "Installing Docker..."
        curl -fsSL https://get.docker.com | sh
        systemctl enable docker
        systemctl start docker
        usermod -aG docker $USER
    fi
    
    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null; then
        print_info "Installing Docker Compose..."
        curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        chmod +x /usr/local/bin/docker-compose
    fi
    
    print_success "System requirements met"
}

# Backup existing configuration
backup_existing_config() {
    print_info "Creating backup of existing configuration..."
    
    mkdir -p "$BACKUP_DIR"
    
    # Backup web files
    cp -r /var/www/html "$BACKUP_DIR/"
    
    # Backup nginx config
    if [[ -d "/etc/nginx" ]]; then
        cp -r /etc/nginx "$BACKUP_DIR/"
    fi
    
    # Create restoration script
    cat > "$BACKUP_DIR/restore.sh" << 'EOF'
#!/bin/bash
echo "Restoring Enhanced Evernode to pre-Traefik state..."
read -p "Are you sure? This will remove Traefik setup. (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    systemctl stop docker || true
    rm -rf /opt/evernode-traefik
    
    if [[ -d "html" ]]; then
        cp -r html/* /var/www/html/
    fi
    
    if [[ -d "nginx" ]]; then
        cp -r nginx/* /etc/nginx/
        systemctl restart nginx
    fi
    
    echo "✅ Restoration completed!"
    echo "Your Enhanced Evernode host is back to basic setup."
else
    echo "Restoration cancelled."
fi
EOF
    chmod +x "$BACKUP_DIR/restore.sh"
    
    print_success "Backup created at $BACKUP_DIR"
}

# Setup Traefik directories and configuration
setup_traefik() {
    print_info "Setting up Traefik configuration..."
    
    # Create directory structure
    mkdir -p "$TRAEFIK_DIR"/{traefik,github-service,instances/{instance-1,instance-2,instance-3},deployments}
    
    # Create environment file
    cat > "$TRAEFIK_DIR/.env" << EOF
# Enhanced Evernode Traefik Configuration
DOMAIN=$DOMAIN
ACME_EMAIL=$EMAIL

# Cloudflare DNS (optional - for wildcard certificates)
# Get token from: https://dash.cloudflare.com/profile/api-tokens
CLOUDFLARE_EMAIL=
CLOUDFLARE_DNS_API_TOKEN=

# GitHub Integration (optional)
# Get token from: https://github.com/settings/tokens
GITHUB_TOKEN=
GITHUB_WEBHOOK_SECRET=

# Tenant Configuration
TENANT_ID_1=
TENANT_DOMAIN_1=tenant1.$DOMAIN
TENANT_IMAGE_1=nginx:alpine
AUTO_DEPLOY_1=false

TENANT_ID_2=
TENANT_DOMAIN_2=tenant2.$DOMAIN
TENANT_IMAGE_2=nginx:alpine
AUTO_DEPLOY_2=false

TENANT_ID_3=
TENANT_DOMAIN_3=tenant3.$DOMAIN
TENANT_IMAGE_3=nginx:alpine
AUTO_DEPLOY_3=false

# Basic Auth for Traefik Dashboard (generate with: htpasswd -n admin)
TRAEFIK_BASIC_AUTH=admin:\$2y\$10\$V5YKJinVkUC5a0VgeOG7WOu0PdKgV2aiJSdIgAhJb6YFCNPjWEGhO
EOF

    # Download Traefik configuration files
    print_info "Downloading Traefik configuration files..."
    
    # Traefik main config
    curl -fsSL "$REPO_BASE/traefik/traefik.yml" -o "$TRAEFIK_DIR/traefik/traefik.yml"
    curl -fsSL "$REPO_BASE/traefik/dynamic.yml" -o "$TRAEFIK_DIR/traefik/dynamic.yml"
    
    # Docker Compose file
    curl -fsSL "$REPO_BASE/traefik/docker-compose.yml" -o "$TRAEFIK_DIR/docker-compose.yml"
    
    # GitHub service
    mkdir -p "$TRAEFIK_DIR/github-service"
    curl -fsSL "$REPO_BASE/traefik/github-service/package.json" -o "$TRAEFIK_DIR/github-service/package.json"
    curl -fsSL "$REPO_BASE/traefik/github-service/index.js" -o "$TRAEFIK_DIR/github-service/index.js"
    
    # Create acme.json with proper permissions
    touch "$TRAEFIK_DIR/traefik/acme.json"
    chmod 600 "$TRAEFIK_DIR/traefik/acme.json"
    
    # Create logs directory
    mkdir -p "$TRAEFIK_DIR/traefik/logs"
    
    # Copy existing enhanced files for serving
    cp -r /var/www/html/* "$TRAEFIK_DIR/instances/enhanced-landing/" 2>/dev/null || true
    
    print_success "Traefik configuration created"
}

# Stop existing services
stop_existing_services() {
    print_info "Preparing for Traefik..."
    
    # Stop nginx temporarily (Traefik will take over port 80/443)
    if systemctl is-active --quiet nginx; then
        systemctl stop nginx
        print_success "Nginx stopped (Traefik will handle routing)"
    fi
    
    # Stop any other services on port 80/443
    lsof -ti:80 | xargs kill -9 2>/dev/null || true
    lsof -ti:443 | xargs kill -9 2>/dev/null || true
    
    print_success "Ports ready for Traefik"
}

# Start Traefik services
start_traefik() {
    print_info "Starting Traefik services..."
    
    cd "$TRAEFIK_DIR"
    
    # Create Docker network
    docker network create traefik-network 2>/dev/null || true
    
    # Install GitHub service dependencies
    if [[ -d "github-service" ]]; then
        cd github-service
        if command -v npm &> /dev/null && [[ -f "package.json" ]]; then
            npm install --production 2>/dev/null || true
        fi
        cd ..
    fi
    
    # Start services
    docker-compose up -d
    
    # Wait for services to start
    sleep 15
    
    # Check if Traefik is running
    if docker-compose ps | grep -q "Up"; then
        print_success "Traefik services started successfully"
    else
        print_error "Failed to start Traefik services"
        docker-compose logs
        return 1
    fi
}

# Verify installation
verify_installation() {
    print_info "Verifying Traefik installation..."
    
    local checks_passed=0
    local total_checks=4
    
    # Check if main site is accessible
    if curl -f -s -H "Host: $DOMAIN" http://localhost/ > /dev/null; then
        print_success "Main site accessible through Traefik"
        ((checks_passed++))
    else
        print_warning "Main site not accessible"
    fi
    
    # Check if Traefik dashboard is accessible
    if curl -f -s http://localhost:8080/api/rawdata > /dev/null; then
        print_success "Traefik dashboard accessible"
        ((checks_passed++))
    else
        print_warning "Traefik dashboard not accessible"
    fi
    
    # Check Docker containers
    if [[ $(docker-compose ps | grep -c "Up") -ge 2 ]]; then
        print_success "Docker containers running"
        ((checks_passed++))
    else
        print_warning "Some Docker containers not running"
    fi
    
    # Check SSL certificate storage
    if [[ -f "$TRAEFIK_DIR/traefik/acme.json" ]]; then
        print_success "SSL certificate storage configured"
        ((checks_passed++))
    else
        print_warning "SSL certificate storage not found"
    fi
    
    echo ""
    print_info "Verification: $checks_passed/$total_checks checks passed"
    
    if [[ $checks_passed -ge 3 ]]; then
        return 0
    else
        return 1
    fi
}

# Generate upgrade report
generate_upgrade_report() {
    print_info "Generating Traefik upgrade report..."
    
    cat > "$TRAEFIK_DIR/upgrade-report.txt" << EOF
🚀 Enhanced Evernode - Traefik Upgrade Complete!
===============================================

Upgrade Date: $(date)
Domain: $DOMAIN
Traefik Directory: $TRAEFIK_DIR
Backup Directory: $BACKUP_DIR

✨ TRANSFORMATION COMPLETE:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🔴 BEFORE (Basic Evernode):
• tenant.yourhost.com:26201  (ugly port numbers)
• Manual SSL setup required
• No custom domain support
• Basic container management

🟢 AFTER (Professional Hosting):
• https://myapp.yourhost.com  (professional subdomains)
• https://myapp.com           (custom domains supported)
• Automatic SSL certificates
• GitHub push-to-deploy
• Zero-downtime updates
• Enterprise-grade features

🌐 ACCESS URLS:
• Main Site: https://$DOMAIN/
• Traefik Dashboard: http://traefik.$DOMAIN:8080/
• GitHub Integration: https://github.$DOMAIN/
• Professional Tenant URLs: https://tenant.$DOMAIN/

🔧 MANAGEMENT:
• Configuration: $TRAEFIK_DIR/.env
• View logs: cd $TRAEFIK_DIR && docker-compose logs
• Restart: cd $TRAEFIK_DIR && docker-compose restart
• Add tenants: Edit .env and restart services

💰 BUSINESS IMPACT:
• Professional URLs attract premium tenants
• Higher tenant satisfaction and retention
• Competitive advantage over basic Evernode hosts
• Additional revenue from GitHub integration services

🛡️ SECURITY & RELIABILITY:
• HTTPS everywhere with auto-redirect
• Health checks and automatic failover
• Security headers (HSTS, CSP, XSS protection)
• Rate limiting and DDoS protection

📊 TENANT BENEFITS:
• Custom domains: myapp.com
• GitHub integration: Push code → automatic deployment
• Zero downtime: Rolling updates with health checks
• Professional branding: No more port numbers in URLs

🚀 NEXT STEPS:
1. Configure Cloudflare DNS for wildcard SSL (optional)
2. Set up GitHub tokens for repository integration
3. Add tenant configurations in .env file
4. Test deployments with sample repositories

🆘 TROUBLESHOOTING:
• Restore previous setup: $BACKUP_DIR/restore.sh
• View service status: cd $TRAEFIK_DIR && docker-compose ps
• Check logs: cd $TRAEFIK_DIR && docker-compose logs
• Restart services: cd $TRAEFIK_DIR && docker-compose restart

Your Enhanced Evernode host is now a professional hosting platform! 🎉
EOF

    # Also update the main installation report
    cat > /var/www/html/installation-report.txt << EOF
🚀 Enhanced Evernode with Traefik Professional Hosting
=====================================================

Installation Date: $(date)
Domain: $DOMAIN
Status: ✅ PROFESSIONAL HOSTING ACTIVE

🌟 PROFESSIONAL FEATURES:
• Enhanced Evernode discovery network (20% commission)
• Traefik professional routing and SSL
• GitHub integration for automated deployments
• Custom domain support for tenants
• Zero-downtime updates and health monitoring

🌐 ACCESS URLS:
• Main Enhanced Site: https://$DOMAIN/
• Traefik Dashboard: http://traefik.$DOMAIN:8080/
• dApp Manager: https://$DOMAIN/cluster/dapp-manager.html
• Host Discovery: https://$DOMAIN/host-discovery.html

💼 TENANT EXPERIENCE:
• Professional URLs: https://myapp.$DOMAIN/
• Custom domains: https://myapp.com
• GitHub deployments: Push to deploy
• Automatic SSL: Enterprise security

💰 EARNING OPPORTUNITIES:
• 20% commission on Enhanced discovery sales
• Premium hosting rates with professional features
• GitHub integration service revenue
• Higher tenant retention = stable income

📊 COMPETITIVE ADVANTAGES:
• Professional appearance rivals AWS/Azure
• Evernode costs with enterprise features
• Automated operations reduce maintenance
• Advanced monitoring and analytics

🔧 MANAGEMENT:
• Traefik Config: $TRAEFIK_DIR/
• Full Documentation: $TRAEFIK_DIR/upgrade-report.txt
• Backup/Restore: $BACKUP_DIR/restore.sh

Your Enhanced Evernode host is now a professional hosting platform! 🚀
EOF

    print_success "Reports generated"
}

# Main upgrade function
main() {
    print_header
    
    # Skip confirmation if auto-install
    if [[ "$AUTO_INSTALL" != "--auto-install" ]]; then
        echo -e "${YELLOW}This will upgrade your Enhanced Evernode host to use Traefik.${NC}"
        echo -e "${YELLOW}Your existing configuration will be backed up safely.${NC}"
        echo ""
        read -p "Transform your host to professional hosting? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "Upgrade cancelled."
            exit 0
        fi
    fi
    
    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root"
        echo "Usage: sudo $0"
        exit 1
    fi
    
    # Run upgrade steps
    check_enhanced_host
    check_requirements
    backup_existing_config
    setup_traefik
    stop_existing_services
    start_traefik
    
    echo ""
    if verify_installation; then
        print_success "🎉 Traefik upgrade completed successfully!"
        echo ""
        generate_upgrade_report
        echo ""
        print_feature "🌟 TRANSFORMATION COMPLETE!"
        echo ""
        print_success "Your Enhanced Evernode host is now professional hosting platform!"
        print_info "📖 Full report: $TRAEFIK_DIR/upgrade-report.txt"
        print_info "🌐 Professional site: https://$DOMAIN/"
        print_info "🔧 Traefik dashboard: http://traefik.$DOMAIN:8080/"
        echo ""
        print_feature "Tenants now get professional URLs instead of ugly port numbers!")
        print_feature "Start attracting premium tenants with enterprise-grade features!")
    else
        print_warning "Upgrade completed with some issues"
        print_info "Check logs: cd $TRAEFIK_DIR && docker-compose logs"
        print_info "Restore if needed: $BACKUP_DIR/restore.sh"
    fi
}

# Run main upgrade
main "$@"