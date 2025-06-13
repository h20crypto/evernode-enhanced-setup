#!/usr/bin/env node
const https = require('https');
const fs = require('fs');

// Read known hosts, check which are online, create cluster_hosts.txt
// (The simplified version I showed earlier)
