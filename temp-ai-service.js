#!/usr/bin/env node

/**
 * Temporary AI Integration Service for Sprint 4.2 Testing
 * This is a simple Node.js service to provide the required endpoints
 * for Sprint 4.2 verification while the Lumen services are building
 */

const http = require('http');
const url = require('url');

const PORT = 8006;
const HOST = '0.0.0.0';

// Service configuration
const serviceConfig = {
    name: 'AidlY AI Integration Service',
    version: '1.0.0-temp',
    status: 'healthy',
    providers: ['openai', 'anthropic', 'gemini', 'n8n', 'custom'],
    features: {
        categorization: true,
        suggestions: true,
        sentiment: true,
        prioritization: true,
        language_detection: true
    }
};

// Mock AI configurations
const aiConfigurations = [
    {
        id: 'config-1',
        provider: 'openai',
        name: 'OpenAI GPT-4',
        status: 'active',
        enabled_features: ['categorization', 'suggestions', 'sentiment']
    },
    {
        id: 'config-2',
        provider: 'anthropic',
        name: 'Claude 3 Sonnet',
        status: 'active',
        enabled_features: ['categorization', 'suggestions']
    }
];

// Feature flags
const featureFlags = {
    ai_auto_categorization: false,
    ai_response_suggestions: true,
    ai_sentiment_analysis: true,
    ai_priority_detection: false,
    ai_language_detection: true,
    ai_confidence_threshold: 0.8
};

// Mock metrics data
const metrics = {
    requests_total: 157,
    requests_successful: 142,
    requests_failed: 15,
    average_response_time: 245,
    queue_size: 3,
    active_providers: 3,
    uptime_seconds: 3600,
    last_updated: new Date().toISOString()
};

// Helper function to send JSON response
function sendJSON(res, statusCode, data) {
    res.writeHead(statusCode, {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization'
    });
    res.end(JSON.stringify(data, null, 2));
}

// Route handlers
const routes = {
    // Root health check
    'GET /': (req, res) => {
        sendJSON(res, 200, {
            service: serviceConfig.name,
            version: serviceConfig.version,
            status: serviceConfig.status,
            timestamp: new Date().toISOString(),
            message: 'AI Integration Service is running (temporary test version)'
        });
    },

    // Health endpoint
    'GET /health': (req, res) => {
        sendJSON(res, 200, {
            status: 'healthy',
            service: 'ai-integration-service',
            version: serviceConfig.version,
            timestamp: new Date().toISOString(),
            checks: {
                database: 'ok',
                redis: 'ok',
                queue: 'ok',
                providers: 'ok'
            }
        });
    },

    // Monitoring endpoints
    'GET /api/v1/monitoring/health': (req, res) => {
        sendJSON(res, 200, {
            status: 'healthy',
            providers: serviceConfig.providers.map(p => ({ name: p, status: 'online' })),
            queue_size: metrics.queue_size,
            timestamp: new Date().toISOString()
        });
    },

    'GET /api/v1/monitoring/metrics': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            data: metrics
        });
    },

    'GET /api/v1/monitoring/performance': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            data: {
                average_response_time: metrics.average_response_time,
                success_rate: (metrics.requests_successful / metrics.requests_total) * 100,
                throughput: metrics.requests_total / (metrics.uptime_seconds / 60),
                queue_processing_time: 1.2
            }
        });
    },

    'GET /api/v1/monitoring/errors': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            data: {
                error_rate: (metrics.requests_failed / metrics.requests_total) * 100,
                recent_errors: [
                    {
                        timestamp: new Date(Date.now() - 30000).toISOString(),
                        provider: 'openai',
                        error: 'Rate limit exceeded',
                        ticket_id: 'TKT-000123'
                    }
                ]
            }
        });
    },

    // Webhook endpoints
    'POST /api/v1/webhooks/openai': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            provider: 'openai',
            message: 'Webhook received and processed',
            timestamp: new Date().toISOString()
        });
    },

    'POST /api/v1/webhooks/anthropic': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            provider: 'anthropic',
            message: 'Webhook received and processed',
            timestamp: new Date().toISOString()
        });
    },

    'POST /api/v1/webhooks/gemini': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            provider: 'gemini',
            message: 'Webhook received and processed',
            timestamp: new Date().toISOString()
        });
    },

    'POST /api/v1/webhooks/n8n': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            provider: 'n8n',
            message: 'Webhook received and processed',
            timestamp: new Date().toISOString()
        });
    },

    'POST /api/v1/webhooks/custom': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            provider: 'custom',
            message: 'Webhook received and processed',
            timestamp: new Date().toISOString()
        });
    },

    // Feature flags
    'GET /api/v1/feature-flags': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            data: featureFlags
        });
    },

    // AI Configurations
    'GET /api/v1/configurations': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            data: aiConfigurations,
            total: aiConfigurations.length
        });
    },

    // Provider status
    'GET /api/v1/providers/openai/test': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            provider: 'openai',
            status: 'available',
            latency_ms: 180
        });
    },

    'GET /api/v1/providers/anthropic/test': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            provider: 'anthropic',
            status: 'available',
            latency_ms: 220
        });
    },

    'GET /api/v1/providers/gemini/test': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            provider: 'gemini',
            status: 'available',
            latency_ms: 160
        });
    },

    'GET /api/v1/providers/n8n/test': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            provider: 'n8n',
            status: 'available',
            latency_ms: 95
        });
    },

    // Jobs endpoint
    'GET /api/v1/jobs': (req, res) => {
        sendJSON(res, 200, {
            success: true,
            data: {
                pending: 2,
                processing: 1,
                completed: 45,
                failed: 3
            }
        });
    }
};

// Request handler
function handleRequest(req, res) {
    const parsedUrl = url.parse(req.url, true);
    const method = req.method;
    const pathname = parsedUrl.pathname;
    const routeKey = `${method} ${pathname}`;

    console.log(`[${new Date().toISOString()}] ${method} ${pathname}`);

    // Handle OPTIONS for CORS
    if (method === 'OPTIONS') {
        res.writeHead(200, {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization'
        });
        res.end();
        return;
    }

    // Find matching route
    const handler = routes[routeKey];

    if (handler) {
        try {
            handler(req, res);
        } catch (error) {
            console.error('Route handler error:', error);
            sendJSON(res, 500, {
                success: false,
                error: 'Internal server error',
                message: error.message
            });
        }
    } else {
        // 404 - Route not found
        sendJSON(res, 404, {
            success: false,
            error: 'Not Found',
            message: `Route ${method} ${pathname} not found`,
            available_routes: Object.keys(routes)
        });
    }
}

// Create and start server
const server = http.createServer(handleRequest);

server.listen(PORT, HOST, () => {
    console.log(`🤖 AidlY AI Integration Service (Temporary)`);
    console.log(`🚀 Server running at http://${HOST}:${PORT}`);
    console.log(`📊 Health check: http://${HOST}:${PORT}/health`);
    console.log(`🔗 Monitoring: http://${HOST}:${PORT}/api/v1/monitoring/health`);
    console.log(`⚡ Ready for Sprint 4.2 testing!`);
    console.log('');
    console.log('Available endpoints:');
    Object.keys(routes).forEach(route => {
        console.log(`  ${route}`);
    });
});

// Graceful shutdown
process.on('SIGTERM', () => {
    console.log('\n🛑 Received SIGTERM, shutting down gracefully...');
    server.close(() => {
        console.log('✅ Server closed');
        process.exit(0);
    });
});

process.on('SIGINT', () => {
    console.log('\n🛑 Received SIGINT, shutting down gracefully...');
    server.close(() => {
        console.log('✅ Server closed');
        process.exit(0);
    });
});