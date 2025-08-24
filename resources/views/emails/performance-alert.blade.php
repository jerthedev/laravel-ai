<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Performance Alert - {{ $severity }} Priority</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: {{ $alert['severity'] === 'critical' ? '#dc3545' : ($alert['severity'] === 'high' ? '#fd7e14' : '#ffc107') }};
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            background: #f8f9fa;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-top: none;
        }
        .alert-details {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .metric {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .metric:last-child {
            border-bottom: none;
        }
        .metric-label {
            font-weight: 600;
            color: #495057;
        }
        .metric-value {
            color: #212529;
        }
        .actions {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .actions h4 {
            margin-top: 0;
            color: #495057;
        }
        .actions ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .actions li {
            margin: 5px 0;
        }
        .button {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 15px 0;
        }
        .footer {
            background: #6c757d;
            color: white;
            padding: 15px;
            border-radius: 0 0 8px 8px;
            text-align: center;
            font-size: 14px;
        }
        .critical-warning {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $severity }} Performance Alert</h1>
        <p>{{ $alert['message'] }}</p>
    </div>

    <div class="content">
        <div class="alert-details">
            <h3>Alert Details</h3>
            <div class="metric">
                <span class="metric-label">Component:</span>
                <span class="metric-value">{{ $component }}</span>
            </div>
            <div class="metric">
                <span class="metric-label">Execution Time:</span>
                <span class="metric-value">{{ $duration }}ms</span>
            </div>
            <div class="metric">
                <span class="metric-label">Threshold:</span>
                <span class="metric-value">{{ $threshold }}ms</span>
            </div>
            <div class="metric">
                <span class="metric-label">Exceeded By:</span>
                <span class="metric-value">{{ $exceededBy }}%</span>
            </div>
            <div class="metric">
                <span class="metric-label">Severity:</span>
                <span class="metric-value">{{ $severity }}</span>
            </div>
            <div class="metric">
                <span class="metric-label">Timestamp:</span>
                <span class="metric-value">{{ $alert['timestamp'] }}</span>
            </div>
        </div>

        @if(!empty($alert['context']))
        <div class="alert-details">
            <h4>Additional Context</h4>
            @foreach($alert['context'] as $key => $value)
            <div class="metric">
                <span class="metric-label">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                <span class="metric-value">{{ $value }}</span>
            </div>
            @endforeach
        </div>
        @endif

        @if(!empty($alert['recommended_actions']))
        <div class="actions">
            <h4>Recommended Actions</h4>
            <ul>
                @foreach($alert['recommended_actions'] as $action)
                <li>{{ $action }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div style="text-align: center;">
            <a href="{{ $dashboardUrl }}" class="button">View Performance Dashboard</a>
        </div>

        @if($alert['severity'] === 'critical')
        <div class="critical-warning">
            <strong>⚠️ Critical Performance Issue</strong><br>
            This is a critical performance issue that requires immediate attention to prevent system degradation.
        </div>
        @endif
    </div>

    <div class="footer">
        <p>Laravel AI Performance Monitor</p>
        <p>Monitor your system performance and view detailed analytics in the dashboard.</p>
    </div>
</body>
</html>
