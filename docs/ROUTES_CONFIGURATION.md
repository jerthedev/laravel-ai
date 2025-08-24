# Routes Configuration

This document explains how to configure the Laravel AI package routes for different environments and use cases.

## Overview

The Laravel AI package provides comprehensive route configuration options to ensure it works well in production environments without conflicts. Routes are organized into separate files and can be individually enabled/disabled.

## Configuration Options

### Environment Variables

Add these to your `.env` file to configure routes:

```env
# Global route control
AI_ROUTES_ENABLED=true

# API routes configuration
AI_API_ROUTES_ENABLED=true
AI_API_PREFIX=ai-admin
AI_PERFORMANCE_ROUTES_ENABLED=true
AI_PERFORMANCE_DASHBOARD_ENABLED=false
AI_COST_ROUTES_ENABLED=true
AI_COST_ANALYTICS_ENABLED=false
AI_MCP_ROUTES_ENABLED=true

# Web dashboard routes (disabled by default)
AI_WEB_ROUTES_ENABLED=false
AI_WEB_PREFIX=ai-dashboard

# Rate limiting
AI_RATE_LIMITING_ENABLED=true
AI_RATE_LIMIT_ATTEMPTS=60
AI_RATE_LIMIT_DECAY=1
```

### Configuration File

The routes are configured in `config/ai.php`:

```php
'routes' => [
    
    // Enable/disable all package routes
    'enabled' => env('AI_ROUTES_ENABLED', true),
    
    // API routes configuration
    'api' => [
        'enabled' => env('AI_API_ROUTES_ENABLED', true),
        'prefix' => env('AI_API_PREFIX', 'ai-admin'),
        'middleware' => ['api'],
        'name_prefix' => 'ai.',
    ],
    
    // Web routes configuration (for future dashboard UI)
    'web' => [
        'enabled' => env('AI_WEB_ROUTES_ENABLED', false),
        'prefix' => env('AI_WEB_PREFIX', 'ai-dashboard'),
        'middleware' => ['web', 'auth'],
        'name_prefix' => 'ai.web.',
    ],
    
    // Performance monitoring routes
    'performance' => [
        'enabled' => env('AI_PERFORMANCE_ROUTES_ENABLED', true),
        'dashboard_enabled' => env('AI_PERFORMANCE_DASHBOARD_ENABLED', false),
    ],
    
    // Cost tracking routes
    'costs' => [
        'enabled' => env('AI_COST_ROUTES_ENABLED', true),
        'analytics_enabled' => env('AI_COST_ANALYTICS_ENABLED', false),
    ],
    
    // MCP management routes
    'mcp' => [
        'enabled' => env('AI_MCP_ROUTES_ENABLED', true),
    ],
    
    // Rate limiting for API routes
    'rate_limiting' => [
        'enabled' => env('AI_RATE_LIMITING_ENABLED', true),
        'max_attempts' => env('AI_RATE_LIMIT_ATTEMPTS', 60),
        'decay_minutes' => env('AI_RATE_LIMIT_DECAY', 1),
    ],
],
```

## Route Structure

### API Routes (`routes/api.php`)

**Default Prefix:** `ai-admin` (configurable)

- **Performance Monitoring:**
  - `GET /ai-admin/performance/alerts` - List alerts (always available)
  - `POST /ai-admin/performance/alerts/{id}/acknowledge` - Acknowledge alert
  - `POST /ai-admin/performance/alerts/{id}/resolve` - Resolve alert
  - `GET /ai-admin/performance/dashboard` - Dashboard data (disabled by default)
  - `GET /ai-admin/performance/components/{component}` - Component metrics
  - `GET /ai-admin/performance/queues` - Queue performance
  - `GET /ai-admin/performance/realtime` - Real-time metrics
  - `GET /ai-admin/performance/trends` - Performance trends
  - `GET /ai-admin/performance/recommendations` - Optimization recommendations
  - `POST /ai-admin/performance/export` - Export reports

- **Cost Tracking:**
  - `GET /ai-admin/costs/current` - Current usage (always available)
  - `GET /ai-admin/costs/analytics` - Detailed analytics (disabled by default)
  - `GET /ai-admin/costs/reports` - Cost reports (disabled by default)
  - `POST /ai-admin/costs/export` - Export cost data (disabled by default)

- **MCP Management:**
  - `GET /ai-admin/mcp/status` - Server status
  - `POST /ai-admin/mcp/servers/{server}/enable` - Enable server
  - `POST /ai-admin/mcp/servers/{server}/disable` - Disable server
  - `POST /ai-admin/mcp/servers/{server}/test` - Test server

- **System Health:**
  - `GET /ai-admin/system/health` - Health check (always available)
  - `GET /ai-admin/system/info` - System information

### Web Routes (`routes/web.php`)

**Default Prefix:** `ai-dashboard` (configurable)
**Default Middleware:** `['web', 'auth']`
**Default Status:** Disabled

- `GET /ai-dashboard/` - Dashboard home
- `GET /ai-dashboard/performance/` - Performance dashboard
- `GET /ai-dashboard/performance/alerts` - Alerts interface
- `GET /ai-dashboard/costs/` - Cost dashboard
- `GET /ai-dashboard/mcp/` - MCP management interface
- `GET /ai-dashboard/settings/` - Settings interface

## Production Recommendations

### Minimal Production Setup

For production environments, use this minimal configuration:

```env
# Enable only essential monitoring
AI_ROUTES_ENABLED=true
AI_API_ROUTES_ENABLED=true
AI_API_PREFIX=ai-admin

# Disable detailed dashboards and analytics
AI_PERFORMANCE_DASHBOARD_ENABLED=false
AI_COST_ANALYTICS_ENABLED=false
AI_WEB_ROUTES_ENABLED=false

# Enable basic monitoring
AI_PERFORMANCE_ROUTES_ENABLED=true
AI_COST_ROUTES_ENABLED=true
AI_MCP_ROUTES_ENABLED=true

# Enable rate limiting
AI_RATE_LIMITING_ENABLED=true
AI_RATE_LIMIT_ATTEMPTS=60
```

This provides:
- ✅ Alert management for monitoring
- ✅ Basic cost tracking
- ✅ MCP server management
- ✅ System health checks
- ❌ Detailed dashboards (security)
- ❌ Analytics endpoints (performance)
- ❌ Web interface (security)

### Development Setup

For development environments:

```env
# Enable everything for development
AI_ROUTES_ENABLED=true
AI_API_ROUTES_ENABLED=true
AI_WEB_ROUTES_ENABLED=true
AI_PERFORMANCE_DASHBOARD_ENABLED=true
AI_COST_ANALYTICS_ENABLED=true
AI_RATE_LIMITING_ENABLED=false
```

### Staging Setup

For staging environments:

```env
# Enable dashboards for testing
AI_ROUTES_ENABLED=true
AI_API_ROUTES_ENABLED=true
AI_WEB_ROUTES_ENABLED=true
AI_PERFORMANCE_DASHBOARD_ENABLED=true
AI_COST_ANALYTICS_ENABLED=true
AI_RATE_LIMITING_ENABLED=true
```

## Security Considerations

### Authentication

- **API Routes:** Use `api` middleware by default (no authentication)
- **Web Routes:** Use `['web', 'auth']` middleware by default (requires authentication)
- **Custom Middleware:** Override in configuration:

```php
'api' => [
    'middleware' => ['api', 'auth:sanctum'], // Add API authentication
],
'web' => [
    'middleware' => ['web', 'auth', 'can:manage-ai'], // Add authorization
],
```

### Route Prefixes

Choose prefixes that don't conflict with your application:

```env
# Avoid common conflicts
AI_API_PREFIX=ai-admin          # Good: specific and unlikely to conflict
AI_WEB_PREFIX=ai-dashboard      # Good: specific and unlikely to conflict

# Avoid these
AI_API_PREFIX=admin             # Bad: likely to conflict
AI_API_PREFIX=api               # Bad: will conflict with Laravel's api routes
AI_WEB_PREFIX=dashboard         # Bad: common application route
```

### Disabling in Production

For maximum security in production, disable unnecessary routes:

```php
// In a service provider or middleware
if (app()->environment('production')) {
    config([
        'ai.routes.performance.dashboard_enabled' => false,
        'ai.routes.costs.analytics_enabled' => false,
        'ai.routes.web.enabled' => false,
    ]);
}
```

## Customization

### Custom Middleware

Add custom middleware for additional security:

```php
'api' => [
    'middleware' => ['api', 'throttle:60,1', 'auth:sanctum', 'can:manage-ai'],
],
```

### Custom Prefixes

Use environment-specific prefixes:

```env
# Production
AI_API_PREFIX=internal-ai-admin

# Staging  
AI_API_PREFIX=staging-ai-admin

# Development
AI_API_PREFIX=dev-ai-admin
```

### Conditional Loading

Conditionally load routes based on environment:

```php
// In AppServiceProvider
public function boot()
{
    if ($this->app->environment('local')) {
        config(['ai.routes.web.enabled' => true]);
        config(['ai.routes.performance.dashboard_enabled' => true]);
    }
}
```

## Testing Routes

Test that routes are properly configured:

```bash
# List all AI package routes
php artisan route:list --name=ai

# Test health endpoint
curl http://your-app.com/ai-admin/system/health

# Test with authentication
curl -H "Authorization: Bearer your-token" \
     http://your-app.com/ai-admin/performance/alerts
```

## Troubleshooting

### Routes Not Loading

1. Check if routes are enabled: `config('ai.routes.enabled')`
2. Check specific route group: `config('ai.routes.api.enabled')`
3. Clear config cache: `php artisan config:clear`
4. Check middleware conflicts

### Route Conflicts

1. Change the route prefix: `AI_API_PREFIX=your-custom-prefix`
2. Check existing routes: `php artisan route:list`
3. Use more specific prefixes

### Performance Issues

1. Disable unnecessary routes in production
2. Enable rate limiting: `AI_RATE_LIMITING_ENABLED=true`
3. Use caching for dashboard data
4. Monitor route performance with the package's own monitoring

This configuration system ensures the Laravel AI package can be safely deployed in any environment while providing the flexibility needed for development and monitoring.
