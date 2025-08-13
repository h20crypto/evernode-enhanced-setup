/**
 * GitHub Integration Service for Enhanced Evernode Hosts
 * Handles repository management, webhooks, and automated deployments
 */

const express = require('express');
const { Octokit } = require('@octokit/rest');
const crypto = require('crypto');
const fs = require('fs').promises;
const path = require('path');
const { exec } = require('child_process');
const util = require('util');
const execAsync = util.promisify(exec);

const app = express();
const port = process.env.PORT || 3000;

// Configuration
const config = {
  github: {
    token: process.env.GITHUB_TOKEN,
    webhookSecret: process.env.GITHUB_WEBHOOK_SECRET
  },
  evernode: {
    hostDomain: process.env.EVERNODE_HOST_DOMAIN || 'localhost',
    deploymentPath: '/deployments',
    instancePath: '/app/instances'
  }
};

// Initialize GitHub API
const octokit = new Octokit({
  auth: config.github.token
});

// Middleware
app.use(express.json({ limit: '10mb' }));
app.use(express.raw({ type: 'application/vnd.github+json', limit: '10mb' }));

// CORS middleware
app.use((req, res, next) => {
  res.header('Access-Control-Allow-Origin', '*');
  res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-GitHub-Event, X-GitHub-Delivery');
  res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  if (req.method === 'OPTIONS') {
    res.sendStatus(200);
  } else {
    next();
  }
});

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({ 
    status: 'healthy', 
    service: 'github-integration',
    timestamp: new Date().toISOString(),
    github_connected: !!config.github.token
  });
});

// Authentication verification endpoint
app.post('/auth/verify', async (req, res) => {
  try {
    const authHeader = req.headers.authorization;
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({ error: 'Missing or invalid authorization header' });
    }

    const token = authHeader.slice(7);
    // For now, we'll accept any valid GitHub token
    // In production, implement proper tenant authentication
    
    res.json({ 
      authenticated: true,
      user: 'tenant',
      tenant_id: 'default'
    });
  } catch (error) {
    console.error('Auth verification error:', error);
    res.status(401).json({ error: 'Authentication failed' });
  }
});

// GitHub webhook verification
function verifyGitHubWebhook(req, res, next) {
  const signature = req.headers['x-hub-signature-256'];
  const payload = req.body;
  
  if (!signature || !config.github.webhookSecret) {
    return res.status(401).json({ error: 'Webhook signature verification failed' });
  }

  const expectedSignature = 'sha256=' + crypto
    .createHmac('sha256', config.github.webhookSecret)
    .update(payload)
    .digest('hex');

  if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expectedSignature))) {
    return res.status(401).json({ error: 'Invalid webhook signature' });
  }

  next();
}

// GitHub webhook endpoint
app.post('/webhook', verifyGitHubWebhook, async (req, res) => {
  try {
    const event = req.headers['x-github-event'];
    const delivery = req.headers['x-github-delivery'];
    const payload = JSON.parse(req.body);

    console.log(`Received GitHub webhook: ${event} (${delivery})`);

    switch (event) {
      case 'push':
        await handlePushEvent(payload);
        break;
      case 'pull_request':
        await handlePullRequestEvent(payload);
        break;
      case 'release':
        await handleReleaseEvent(payload);
        break;
      default:
        console.log(`Unhandled event type: ${event}`);
    }

    res.json({ status: 'processed', event, delivery });
  } catch (error) {
    console.error('Webhook processing error:', error);
    res.status(500).json({ error: 'Webhook processing failed' });
  }
});

// Handle push events for auto-deployment
async function handlePushEvent(payload) {
  const { repository, ref, commits } = payload;
  const branch = ref.replace('refs/heads/', '');
  
  console.log(`Push to ${repository.full_name}:${branch} with ${commits.length} commits`);

  // Find matching instance for this repository
  const instances = await getInstancesForRepo(repository.full_name);
  
  for (const instance of instances) {
    if (instance.autoDeploy && instance.branch === branch) {
      await deployToInstance(instance, repository, branch);
    }
  }
}

// Handle pull request events
async function handlePullRequestEvent(payload) {
  const { action, pull_request, repository } = payload;
  
  if (action === 'opened' || action === 'synchronize') {
    console.log(`PR ${pull_request.number} ${action} in ${repository.full_name}`);
    
    // Create preview deployment
    await createPreviewDeployment(repository, pull_request);
  }
}

// Handle release events
async function handleReleaseEvent(payload) {
  const { action, release, repository } = payload;
  
  if (action === 'published') {
    console.log(`Release ${release.tag_name} published in ${repository.full_name}`);
    
    // Deploy to production instances
    await deployReleaseToProduction(repository, release);
  }
}

// Get instances configured for a repository
async function getInstancesForRepo(repoFullName) {
  try {
    const instancesDir = '/deployments';
    const instances = [];
    
    for (let i = 1; i <= 3; i++) {
      const configPath = path.join(instancesDir, `instance-${i}`, 'config.json');
      try {
        const configData = await fs.readFile(configPath, 'utf8');
        const config = JSON.parse(configData);
        
        if (config.githubRepo === repoFullName) {
          instances.push({
            id: i,
            ...config,
            path: path.join(instancesDir, `instance-${i}`)
          });
        }
      } catch (error) {
        // Config file doesn't exist or is invalid, skip
      }
    }
    
    return instances;
  } catch (error) {
    console.error('Error getting instances for repo:', error);
    return [];
  }
}

// Deploy repository to instance
async function deployToInstance(instance, repository, branch = 'main') {
  try {
    console.log(`Deploying ${repository.full_name}:${branch} to instance ${instance.id}`);
    
    const deployPath = path.join(instance.path, 'deployment');
    
    // Clone or pull repository
    await ensureGitRepo(deployPath, repository.clone_url, branch);
    
    // Run deployment script if exists
    const deployScript = path.join(deployPath, 'deploy.sh');
    try {
      await fs.access(deployScript);
      console.log('Running deployment script...');
      await execAsync(`cd ${deployPath} && chmod +x deploy.sh && ./deploy.sh`);
    } catch (error) {
      console.log('No deployment script found, using default deployment');
      await defaultDeployment(deployPath, instance);
    }
    
    // Update Traefik labels for the instance
    await updateTraefikLabels(instance, repository);
    
    console.log(`Deployment completed for instance ${instance.id}`);
  } catch (error) {
    console.error(`Deployment failed for instance ${instance.id}:`, error);
    throw error;
  }
}

// Ensure git repository is cloned and up to date
async function ensureGitRepo(targetPath, repoUrl, branch) {
  try {
    // Check if directory exists and is a git repo
    await fs.access(path.join(targetPath, '.git'));
    console.log('Pulling latest changes...');
    await execAsync(`cd ${targetPath} && git pull origin ${branch}`);
  } catch (error) {
    // Directory doesn't exist or isn't a git repo, clone it
    console.log('Cloning repository...');
    await execAsync(`rm -rf ${targetPath} && git clone -b ${branch} ${repoUrl} ${targetPath}`);
  }
}

// Default deployment process
async function defaultDeployment(sourcePath, instance) {
  const targetPath = `/app/instances/instance-${instance.id}`;
  
  // Copy files to nginx directory
  await execAsync(`cp -r ${sourcePath}/* ${targetPath}/`);
  
  // Restart container if needed
  await execAsync(`docker restart evernode-inst-${instance.id}`);
}

// Update Traefik labels for dynamic routing
async function updateTraefikLabels(instance, repository) {
  try {
    const containerName = `evernode-inst-${instance.id}`;
    const domain = instance.customDomain || `${repository.name}.${config.evernode.hostDomain}`;
    
    const labels = {
      'traefik.enable': 'true',
      [`traefik.http.routers.${containerName}.rule`]: `Host(\`${domain}\`)`,
      [`traefik.http.routers.${containerName}.tls`]: 'true',
      [`traefik.http.routers.${containerName}.tls.certresolver`]: 'cloudflare',
      [`traefik.http.services.${containerName}.loadbalancer.server.port`]: '80'
    };
    
    // Apply labels to container
    const labelArgs = Object.entries(labels).map(([key, value]) => `--label ${key}=${value}`).join(' ');
    await execAsync(`docker update ${labelArgs} ${containerName}`);
    
    console.log(`Updated Traefik labels for ${containerName}`);
  } catch (error) {
    console.error('Error updating Traefik labels:', error);
  }
}

// API Routes

// Get repository information
app.get('/api/repos/:owner/:repo', async (req, res) => {
  try {
    const { owner, repo } = req.params;
    
    const response = await octokit.rest.repos.get({
      owner,
      repo
    });
    
    res.json(response.data);
  } catch (error) {
    console.error('Error fetching repository:', error);
    res.status(404).json({ error: 'Repository not found' });
  }
});

// Get repository branches
app.get('/api/repos/:owner/:repo/branches', async (req, res) => {
  try {
    const { owner, repo } = req.params;
    
    const response = await octokit.rest.repos.listBranches({
      owner,
      repo
    });
    
    res.json(response.data);
  } catch (error) {
    console.error('Error fetching branches:', error);
    res.status(500).json({ error: 'Failed to fetch branches' });
  }
});

// Create deployment configuration
app.post('/api/instances/:id/deploy', async (req, res) => {
  try {
    const { id } = req.params;
    const { githubRepo, branch = 'main', autoDeploy = false, customDomain } = req.body;
    
    const configPath = path.join('/deployments', `instance-${id}`, 'config.json');
    const config = {
      githubRepo,
      branch,
      autoDeploy,
      customDomain,
      createdAt: new Date().toISOString()
    };
    
    // Ensure directory exists
    await fs.mkdir(path.dirname(configPath), { recursive: true });
    await fs.writeFile(configPath, JSON.stringify(config, null, 2));
    
    // Trigger initial deployment
    const [owner, repo] = githubRepo.split('/');
    const repoData = await octokit.rest.repos.get({ owner, repo });
    
    await deployToInstance({ id, ...config, path: path.dirname(configPath) }, repoData.data, branch);
    
    res.json({ success: true, config });
  } catch (error) {
    console.error('Error creating deployment config:', error);
    res.status(500).json({ error: 'Failed to create deployment configuration' });
  }
});

// Get instance configuration
app.get('/api/instances/:id/config', async (req, res) => {
  try {
    const { id } = req.params;
    const configPath = path.join('/deployments', `instance-${id}`, 'config.json');
    
    const configData = await fs.readFile(configPath, 'utf8');
    const config = JSON.parse(configData);
    
    res.json(config);
  } catch (error) {
    res.status(404).json({ error: 'Configuration not found' });
  }
});

// Create preview deployment for PR
async function createPreviewDeployment(repository, pullRequest) {
  // Implementation for preview deployments
  console.log(`Creating preview deployment for PR #${pullRequest.number}`);
}

// Deploy release to production
async function deployReleaseToProduction(repository, release) {
  // Implementation for production deployments
  console.log(`Deploying release ${release.tag_name} to production`);
}

// Start server
app.listen(port, () => {
  console.log(`GitHub Integration Service running on port ${port}`);
  console.log('Environment:', {
    github_token: !!config.github.token,
    webhook_secret: !!config.github.webhookSecret,
    host_domain: config.evernode.hostDomain
  });
});