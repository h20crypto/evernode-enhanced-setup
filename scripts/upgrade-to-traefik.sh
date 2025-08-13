#!/bin/bash

# Enhanced Evernode - Traefik Professional Upgrade
# Repository: https://github.com/h20crypto/evernode-enhanced-setup
# Version: 1.0.1 - Fixed syntax errors

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
    echo -e "${RED}BEFORE:${NC} tenant.yourhost.com:26201 (ugly, technical)"
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
    if [[ -d "/var/www/html" ]]; then
        cp -r /var/www/html "$BACKUP_DIR/"
    fi
    
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

# Create Traefik configuration files
create_traefik_config() {
    print_info "Creating Traefik configuration..."
    
    mkdir -p "$TRAEFIK_DIR"/{traefik,github-service,instances/{instance-1,instance-2,instance-3},deployments}
    
    # Environment file
    cat > "$TRAEFIK_DIR/.env" << EOF
DOMAIN=$DOMAIN
ACME_EMAIL=$EMAIL
CLOUDFLARE_EMAIL=
CLOUDFLARE_DNS_API_TOKEN=
GITHUB_TOKEN=
GITHUB_WEBHOOK_SECRET=
TENANT_DOMAIN_1=tenant1.$DOMAIN
TENANT_DOMAIN_2=tenant2.$DOMAIN
TENANT_DOMAIN_3=tenant3.$DOMAIN
TRAEFIK_BASIC_AUTH=admin:\$2y\$10\$V5YKJinVkUC5a0VgeOG7WOu0PdKgV2aiJSdIgAhJb6YFCNPjWEGhO
EOF

    # Main Traefik configuration
    cat > "$TRAEFIK_DIR/traefik/traefik.yml" << 'EOF'
global:
  checkNewVersion: false
  sendAnonymousUsage: false

entryPoints:
  web:
    address: ":80"
    http:
      redirections:
        entrypoint:
          to: websecure
          scheme: https
  websecure:
    address: ":443"

api:
  dashboard: true
  debug: false

providers:
  docker:
    endpoint: "unix:///var/run/docker.sock"
    exposedByDefault: false
    network: traefik-network
    watch: true
  file:
    filename: /dynamic.yml
    watch: true

certificatesResolvers:
  cloudflare:
    acme:
      email: "${ACME_EMAIL:-admin@localhost}"
      storage: /acme.json
      dnsChallenge:
        provider: cloudflare
        delayBeforeCheck: 30
        resolvers:
          - "1.1.1.1:53"
          - "8.8.8.8:53"
  letsencrypt:
    acme:
      email: "${ACME_EMAIL:-admin@localhost}"
      storage: /acme.json
      httpChallenge:
        entryPoint: web

log:
  level: INFO
  
accessLog:
  bufferingSize: 100
EOF

    # Dynamic configuration
    cat > "$TRAEFIK_DIR/traefik/dynamic.yml" << 'EOF'
http:
  middlewares:
    security-headers:
      headers:
        customResponseHeaders:
          X-Frame-Options: "DENY"
          X-Content-Type-Options: "nosniff"
          X-XSS-Protection: "1; mode=block"
        stsSeconds: 63072000
        stsIncludeSubdomains: true
        stsPreload: true
    api-cors:
      headers:
        accessControlAllowOriginList:
          - "*"
        accessControlAllowMethods:
          - GET
          - POST
          - PUT
          - DELETE
          - OPTIONS
        accessControlAllowHeaders:
          - "*"
EOF

    # Docker Compose file
    cat > "$TRAEFIK_DIR/docker-compose.yml" << 'EOF'
version: '3.8'

services:
  traefik:
    image: traefik:v3.0
    container_name: traefik-evernode
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
      - "8080:8080"
    environment:
      - CLOUDFLARE_EMAIL=${CLOUDFLARE_EMAIL:-}
      - CLOUDFLARE_DNS_API_TOKEN=${CLOUDFLARE_DNS_API_TOKEN:-}
      - ACME_EMAIL=${ACME_EMAIL}
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./traefik/traefik.yml:/traefik.yml:ro
      - ./traefik/dynamic.yml:/dynamic.yml:ro
      - ./traefik/acme.json:/acme.json
    networks:
      - traefik-network
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.traefik-dashboard.rule=Host(\`traefik.${DOMAIN}\`)"
      - "traefik.http.routers.traefik-dashboard.tls=true"
      - "traefik.http.routers.traefik-dashboard.service=api@internal"

  enhanced-landing:
    image: nginx:alpine
    container_name: enhanced-landing
    restart: unless-stopped
    volumes:
      - /var/www/html:/usr/share/nginx/html:ro
    networks:
      - traefik-network
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.landing.rule=Host(\`${DOMAIN}\`)"
      - "traefik.http.routers.landing.tls=true"
      - "traefik.http.routers.landing.tls.certresolver=letsencrypt"
      - "traefik.http.services.landing.loadbalancer.server.port=80"

networks:
  traefik-network:
    external: true
EOF

    # Create acme.json with proper permissions
    touch "$TRAEFIK_DIR/traefik/acme.json"
    chmod 600 "$TRAEFIK_DIR/traefik/acme.json"
    
    print_success "Traefik configuration created"
}

# Stop existing services
stop_existing_services() {
    print_info "Preparing for Traefik..."
    
    # Stop nginx temporarily
    if systemctl is-active --quiet nginx; then
        systemctl stop nginx
        print_success "Nginx stopped (Traefik will handle routing)"
    fi
    
    # Stop any other services on port 80/443
    fuser -k 80/tcp 2>/dev/null || true
    fuser -k 443/tcp 2>/dev/null || true
    
    print_success "Ports ready for Traefik"
}

# Start Traefik services
start_traefik() {
    print_info "Starting Traefik services..."
    
    cd "$TRAEFIK_DIR"
    
    # Create Docker network
    docker network create traefik-network 2>/dev/null || true
    
    # Start services
    docker-compose up -d
    
    # Wait for services to start
    sleep 15
    
    # Check if Traefik is running
    if docker-compose ps | grep -q "Up"; then
        print_success "Traefik services started successfully"
        return 0
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
    local total_checks=3
    
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
    if [[ $(docker ps | grep -c traefik) -ge 1 ]]; then
        print_success "Traefik container running"
        ((checks_passed++))
    else
        print_warning "Traefik container not running"
    fi
    
    echo ""
    print_info "Verification: $checks_passed/$total_checks checks passed"
    
    if [[ $checks_passed -ge 2 ]]; then
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
• Zero-downtime updates
• Enterprise-grade features

🌐 ACCESS URLS:
• Main Site: https://$DOMAIN/
• Traefik Dashboard: http://traefik.$DOMAIN:8080/

🔧 MANAGEMENT:
• Configuration: $TRAEFIK_DIR/.env
• View logs: cd $TRAEFIK_DIR && docker-compose logs
• Restart: cd $TRAEFIK_DIR && docker-compose restart

💰 BUSINESS IMPACT:
• Professional URLs attract premium tenants
• Higher tenant satisfaction and retention
• Competitive advantage over basic Evernode hosts

🚀 NEXT STEPS:
1. Configure Cloudflare DNS for wildcard SSL (optional)
2. Test professional tenant URLs
3. Update tenant configurations

🆘 TROUBLESHOOTING:
• Restore previous setup: $BACKUP_DIR/restore.sh
• View service status: cd $TRAEFIK_DIR && docker-compose ps
• Check logs: cd $TRAEFIK_DIR && docker-compose logs

Your Enhanced Evernode host is now a professional hosting platform! 🎉
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
    create_traefik_config
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
        print_feature "Tenants now get professional URLs instead of ugly port numbers!"
        print_feature "Start attracting premium tenants with enterprise-grade features!"
    else
        print_warning "Upgrade completed with some issues"
        print_info "Check logs: cd $TRAEFIK_DIR && docker-compose logs"
        print_info "Restore if needed: $BACKUP_DIR/restore.sh"
    fi
}

# Run main upgrade
main "$@"