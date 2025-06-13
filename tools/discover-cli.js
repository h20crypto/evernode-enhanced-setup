#!/usr/bin/env node
/**
 * Enhanced Host Discovery CLI
 * Usage: node tools/discover-cli.js [--location us] [--available-only]
 */

const https = require('https');
const fs = require('fs');
const path = require('path');

class HostDiscovery {
    constructor() {
        this.knownHostsFile = path.join(__dirname, '../data/enhanced-hosts.json');
    }
    
    async loadKnownHosts() {
        try {
            const data = fs.readFileSync(this.knownHostsFile, 'utf8');
            return JSON.parse(data).hosts;
        } catch (error) {
            console.error('Error loading known hosts:', error.message);
            return [];
        }
    }
    
    async checkHost(domain) {
        return new Promise((resolve) => {
            const req = https.get(`https://${domain}/api/host-info.php`, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    try {
                        resolve(JSON.parse(data));
                    } catch {
                        resolve(null);
                    }
                });
            });
            
            req.on('error', () => resolve(null));
            req.setTimeout(5000, () => {
                req.destroy();
                resolve(null);
            });
        });
    }
    
    async discover(filters = {}) {
        console.log('🔍 Discovering enhanced hosts...\n');
        
        const knownHosts = await this.loadKnownHosts();
        const results = [];
        
        for (const host of knownHosts) {
            process.stdout.write(`Checking ${host.domain}... `);
            
            const status = await this.checkHost(host.domain);
            
            if (status && status.enhanced) {
                console.log('✅ Online');
                
                // Apply filters
                if (filters.availableOnly && status.instances.available === 0) continue;
                if (filters.location && !host.location?.toLowerCase().includes(filters.location)) continue;
                
                results.push({ ...host, ...status });
            } else {
                console.log('❌ Offline');
            }
        }
        
        return results;
    }
    
    displayResults(hosts) {
        if (hosts.length === 0) {
            console.log('\n❌ No enhanced hosts found matching criteria\n');
            return;
        }
        
        console.log(`\n🚀 Found ${hosts.length} enhanced host(s):\n`);
        
        hosts.forEach((host, i) => {
            console.log(`${i + 1}. ${host.domain}`);
            console.log(`   Address: ${host.xahau_address}`);
            console.log(`   Available: ${host.instances.available}/${host.instances.total} slots`);
            console.log(`   Features: ${host.features.join(', ')}`);
            console.log(`   Location: ${host.location || 'Unknown'}`);
            console.log('');
        });
        
        // Generate hosts.txt for cluster creation
        const addresses = hosts.map(h => h.xahau_address);
        fs.writeFileSync('cluster_hosts.txt', addresses.join('\n'));
        console.log('📋 Saved addresses to cluster_hosts.txt');
        console.log('💡 Create cluster: evdevkit cluster-create /path/to/contract cluster_hosts.txt -m 24\n');
    }
}

// CLI interface
async function main() {
    const args = process.argv.slice(2);
    
    if (args.includes('--help')) {
        console.log(`
🔍 Enhanced Host Discovery CLI

Usage:
  node tools/discover-cli.js [options]

Options:
  --location <region>     Filter by location (us, eu, asia)
  --available-only        Show only hosts with available slots
  --help                  Show this help

Examples:
  node tools/discover-cli.js
  node tools/discover-cli.js --location us --available-only
        `);
        return;
    }
    
    const filters = {
        location: args[args.indexOf('--location') + 1] || '',
        availableOnly: args.includes('--available-only')
    };
    
    const discovery = new HostDiscovery();
    const hosts = await discovery.discover(filters);
    discovery.displayResults(hosts);
}

if (require.main === module) {
    main().catch(console.error);
}
