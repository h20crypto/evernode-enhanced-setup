#!/usr/bin/env node
/**
 * Enhanced Host Discovery CLI
 * Usage: node discover-cli.js [--location us] [--available-only]
 */

const https = require('https');
const fs = require('fs');

class HostDiscovery {
    constructor() {
        this.knownHosts = [
    // Users will add their own hosts here
];
    }

    async discoverHosts(options = {}) {
        console.log('🔍 Discovering enhanced hosts...\n');
        
        const availableHosts = [];
        
        for (const host of this.knownHosts) {
            try {
                const hostInfo = await this.checkHost(host.domain);
                if (hostInfo && hostInfo.enhanced) {
                    if (options.availableOnly && hostInfo.instances.available === 0) {
                        continue;
                    }
                    
                    if (options.location && !host.location.toLowerCase().includes(options.location.toLowerCase())) {
                        continue;
                    }
                    
                    availableHosts.push({
                        ...host,
                        ...hostInfo,
                        status: hostInfo.instances.available > 0 ? '✅ Available' : '⚠️ Full'
                    });
                }
            } catch (error) {
                console.log(`❌ ${host.domain}: Offline or not enhanced`);
            }
        }
        
        this.displayResults(availableHosts);
        this.generateClusterFile(availableHosts);
        
        return availableHosts;
    }

    async checkHost(domain) {
        return new Promise((resolve, reject) => {
            const options = {
                hostname: domain,
                port: 443,
                path: '/api/host-info.php',
                method: 'GET',
                timeout: 5000
            };

            const req = https.request(options, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    try {
                        resolve(JSON.parse(data));
                    } catch (error) {
                        reject(error);
                    }
                });
            });

            req.on('error', reject);
            req.on('timeout', () => reject(new Error('Timeout')));
            req.setTimeout(5000);
            req.end();
        });
    }

    displayResults(hosts) {
        if (hosts.length === 0) {
            console.log('❌ No enhanced hosts found matching your criteria\n');
            return;
        }

        console.log(`✅ Found ${hosts.length} enhanced host(s):\n`);
        
        hosts.forEach(host => {
            console.log(`🏠 ${host.domain}`);
            console.log(`   Location: ${host.location}`);
            console.log(`   Operator: ${host.operator}`);
            console.log(`   Available: ${host.instances.available}/${host.instances.total} instances`);
            console.log(`   Status: ${host.status}`);
            console.log(`   Features: ${host.features.join(', ')}`);
            console.log('');
        });
    }

    generateClusterFile(hosts) {
        const availableHosts = hosts.filter(h => h.instances.available > 0);
        
        if (availableHosts.length === 0) {
            console.log('⚠️ No hosts with available capacity for cluster creation\n');
            return;
        }

        const hostList = availableHosts.map(h => h.xahau_address).join('\n');
        
        try {
            fs.writeFileSync('cluster_hosts.txt', hostList);
            console.log('📝 Generated cluster_hosts.txt for evdevkit:');
            console.log(`   evdevkit acquire -i your-contract:latest cluster_hosts.txt -m 24\n`);
        } catch (error) {
            console.log('❌ Could not write cluster_hosts.txt file\n');
        }
    }
}

// CLI Usage
const args = process.argv.slice(2);
const options = {};

for (let i = 0; i < args.length; i++) {
    if (args[i] === '--location' && args[i + 1]) {
        options.location = args[i + 1];
        i++;
    } else if (args[i] === '--available-only') {
        options.availableOnly = true;
    } else if (args[i] === '--help') {
        console.log(`
Enhanced Host Discovery CLI

Usage: node discover-cli.js [options]

Options:
  --location <region>    Filter by location (us, eu, asia)
  --available-only       Only show hosts with available capacity
  --help                Show this help message

Examples:
  node discover-cli.js                    # Find all enhanced hosts
  node discover-cli.js --available-only   # Only available hosts
  node discover-cli.js --location eu      # European hosts only
        `);
        process.exit(0);
    }
}

// Run discovery
const discovery = new HostDiscovery();
discovery.discoverHosts(options).catch(console.error);
