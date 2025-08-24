<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Laravel AI Dashboard' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background: #f8f9fa;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .nav {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .nav a {
            padding: 10px 20px;
            background: white;
            text-decoration: none;
            color: #333;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }
        .nav a:hover {
            background: #007bff;
            color: white;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .status.healthy {
            background: #d4edda;
            color: #155724;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $title ?? 'Laravel AI Dashboard' }}</h1>
            <p>Monitor and manage your AI integrations, performance, and costs.</p>
            <p><strong>Version:</strong> {{ $version ?? '1.0.0' }} | <strong>Environment:</strong> {{ app()->environment() }}</p>
        </div>

        <div class="nav">
            <a href="{{ route('ai.web.dashboard') }}">Dashboard</a>
            <a href="{{ route('ai.web.performance.index') }}">Performance</a>
            <a href="{{ route('ai.web.costs.index') }}">Costs</a>
            <a href="{{ route('ai.web.mcp.index') }}">MCP Servers</a>
            <a href="{{ route('ai.web.settings.index') }}">Settings</a>
        </div>

        <div class="grid">
            <div class="card">
                <h3>System Status</h3>
                <p><span class="status healthy">Healthy</span></p>
                <p>All systems operational</p>
                <ul>
                    <li>API Routes: <span class="status {{ config('ai.routes.api.enabled') ? 'healthy' : 'warning' }}">{{ config('ai.routes.api.enabled') ? 'Enabled' : 'Disabled' }}</span></li>
                    <li>Performance Monitoring: <span class="status {{ config('ai.routes.performance.enabled') ? 'healthy' : 'warning' }}">{{ config('ai.routes.performance.enabled') ? 'Enabled' : 'Disabled' }}</span></li>
                    <li>Cost Tracking: <span class="status {{ config('ai.routes.costs.enabled') ? 'healthy' : 'warning' }}">{{ config('ai.routes.costs.enabled') ? 'Enabled' : 'Disabled' }}</span></li>
                </ul>
            </div>

            <div class="card">
                <h3>Performance Overview</h3>
                <p>Real-time performance metrics</p>
                <ul>
                    <li>Average Response Time: <strong>45ms</strong></li>
                    <li>Active Alerts: <strong>0</strong></li>
                    <li>System Load: <strong>Low</strong></li>
                </ul>
                <a href="{{ route('ai.web.performance.index') }}">View Details →</a>
            </div>

            <div class="card">
                <h3>Cost Summary</h3>
                <p>Current usage and budget status</p>
                <ul>
                    <li>Current Usage: <strong>$0.00</strong></li>
                    <li>Budget Remaining: <strong>100%</strong></li>
                    <li>This Month: <strong>$0.00</strong></li>
                </ul>
                <a href="{{ route('ai.web.costs.index') }}">View Details →</a>
            </div>

            <div class="card">
                <h3>MCP Servers</h3>
                <p>Model Context Protocol server status</p>
                <ul>
                    <li>Active Servers: <strong>0</strong></li>
                    <li>Available Tools: <strong>0</strong></li>
                    <li>Status: <span class="status healthy">Ready</span></li>
                </ul>
                <a href="{{ route('ai.web.mcp.index') }}">Manage Servers →</a>
            </div>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="{{ route('ai.web.performance.alerts') }}" style="padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">View Alerts</a>
                <a href="{{ route('ai.web.costs.analytics') }}" style="padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;">Cost Analytics</a>
                <a href="{{ route('ai.web.mcp.servers') }}" style="padding: 8px 16px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;">MCP Setup</a>
                <a href="{{ route('ai.web.settings.index') }}" style="padding: 8px 16px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Settings</a>
            </div>
        </div>

        <div class="card">
            <h3>Configuration Status</h3>
            <div class="grid">
                <div>
                    <h4>Routes</h4>
                    <ul>
                        <li>API Prefix: <code>{{ config('ai.routes.api.prefix') }}</code></li>
                        <li>Web Prefix: <code>{{ config('ai.routes.web.prefix') }}</code></li>
                        <li>Dashboard: {{ config('ai.routes.performance.dashboard_enabled') ? 'Enabled' : 'Disabled' }}</li>
                    </ul>
                </div>
                <div>
                    <h4>Features</h4>
                    <ul>
                        <li>Performance Alerts: {{ config('ai.performance.alerts.enabled') ? 'Enabled' : 'Disabled' }}</li>
                        <li>Cost Analytics: {{ config('ai.routes.costs.analytics_enabled') ? 'Enabled' : 'Disabled' }}</li>
                        <li>MCP Integration: {{ config('ai.routes.mcp.enabled') ? 'Enabled' : 'Disabled' }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
