<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Analytics Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #007bff;
            font-size: 24px;
            margin: 0 0 10px 0;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .metadata {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .metadata h3 {
            margin-top: 0;
            color: #495057;
        }
        
        .metadata-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .metadata-item {
            display: flex;
            justify-content: space-between;
        }
        
        .metadata-label {
            font-weight: bold;
            color: #495057;
        }
        
        .section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        
        .section h2 {
            color: #007bff;
            font-size: 18px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .section h3 {
            color: #495057;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .table th,
        .table td {
            border: 1px solid #dee2e6;
            padding: 8px 12px;
            text-align: left;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        
        .table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .metric-card {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            display: inline-block;
            width: 200px;
            margin-right: 15px;
        }
        
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .metric-label {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .trend-indicator {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .trend-up {
            background-color: #d4edda;
            color: #155724;
        }
        
        .trend-down {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .trend-stable {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .recommendations {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .recommendations h4 {
            color: #856404;
            margin-top: 0;
        }
        
        .recommendations ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .recommendations li {
            margin-bottom: 5px;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
            
            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>AI Analytics Report</h1>
        <div class="subtitle">
            {{ ucfirst(str_replace('_', ' ', $data['metadata']['report_type'])) }} Report
        </div>
    </div>

    {{-- Metadata Section --}}
    <div class="metadata">
        <h3>Report Information</h3>
        <div class="metadata-grid">
            <div class="metadata-item">
                <span class="metadata-label">Generated:</span>
                <span>{{ \Carbon\Carbon::parse($data['metadata']['generated_at'])->format('M j, Y g:i A') }}</span>
            </div>
            <div class="metadata-item">
                <span class="metadata-label">Report Type:</span>
                <span>{{ ucfirst(str_replace('_', ' ', $data['metadata']['report_type'])) }}</span>
            </div>
            <div class="metadata-item">
                <span class="metadata-label">Date Range:</span>
                <span>{{ ucfirst($data['metadata']['date_range']) }}</span>
            </div>
            <div class="metadata-item">
                <span class="metadata-label">User ID:</span>
                <span>{{ $data['metadata']['user_id'] }}</span>
            </div>
        </div>
        
        @if(!empty($data['metadata']['filters']['providers']) || !empty($data['metadata']['filters']['models']))
            <h4>Applied Filters</h4>
            @if(!empty($data['metadata']['filters']['providers']))
                <p><strong>Providers:</strong> {{ implode(', ', $data['metadata']['filters']['providers']) }}</p>
            @endif
            @if(!empty($data['metadata']['filters']['models']))
                <p><strong>Models:</strong> {{ implode(', ', $data['metadata']['filters']['models']) }}</p>
            @endif
        @endif
    </div>

    {{-- Cost Breakdown Section --}}
    @if(isset($data['cost_breakdown']))
        <div class="section">
            <h2>Cost Breakdown Analysis</h2>
            
            {{-- Summary Metrics --}}
            @if(isset($data['cost_breakdown']['totals']))
                <div style="margin-bottom: 30px;">
                    <div class="metric-card">
                        <div class="metric-value">${{ number_format($data['cost_breakdown']['totals']['total_cost'], 2) }}</div>
                        <div class="metric-label">Total Cost</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">{{ number_format($data['cost_breakdown']['totals']['total_requests']) }}</div>
                        <div class="metric-label">Total Requests</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">${{ number_format($data['cost_breakdown']['totals']['avg_cost_per_request'], 4) }}</div>
                        <div class="metric-label">Avg Cost/Request</div>
                    </div>
                </div>
            @endif

            {{-- Provider Breakdown Table --}}
            @if(isset($data['cost_breakdown']['by_provider']))
                <h3>Cost by Provider</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Total Cost</th>
                            <th>Requests</th>
                            <th>Avg Cost/Request</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['cost_breakdown']['by_provider'] as $provider)
                            <tr>
                                <td>{{ ucfirst($provider['provider']) }}</td>
                                <td>${{ number_format($provider['total_cost'], 2) }}</td>
                                <td>{{ number_format($provider['request_count']) }}</td>
                                <td>${{ number_format($provider['avg_cost_per_request'], 4) }}</td>
                                <td>{{ number_format($provider['percentage'], 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- Usage Trends Section --}}
    @if(isset($data['usage_trends']))
        <div class="section">
            <h2>Usage Trends Analysis</h2>
            
            @if(isset($data['usage_trends']['trend_analysis']))
                <h3>Trend Summary</h3>
                @php
                    $trend = $data['usage_trends']['trend_analysis'];
                    $trendClass = match($trend['trend_direction']) {
                        'increasing' => 'trend-up',
                        'decreasing' => 'trend-down',
                        default => 'trend-stable'
                    };
                @endphp
                
                <p>
                    Usage trend is <span class="trend-indicator {{ $trendClass }}">{{ ucfirst($trend['trend_direction']) }}</span>
                    with a growth rate of <strong>{{ number_format($trend['growth_rate'], 1) }}%</strong>
                    and <strong>{{ ucfirst($trend['trend_strength']) }}</strong> trend strength.
                </p>
                
                <div style="margin-bottom: 20px;">
                    <div class="metric-card">
                        <div class="metric-value">{{ number_format($trend['current_value']) }}</div>
                        <div class="metric-label">Current Usage</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">{{ number_format($trend['average_value']) }}</div>
                        <div class="metric-label">Average Usage</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">{{ number_format($trend['volatility'], 1) }}%</div>
                        <div class="metric-label">Volatility</div>
                    </div>
                </div>
            @endif

            {{-- Forecasting --}}
            @if(isset($data['usage_trends']['forecasting']))
                <h3>Usage Forecast</h3>
                @php $forecast = $data['usage_trends']['forecasting']; @endphp
                <p>
                    <strong>Forecast Method:</strong> {{ ucfirst(str_replace('_', ' ', $forecast['forecast_method'])) }}<br>
                    <strong>Model Accuracy:</strong> {{ number_format($forecast['model_accuracy'] * 100, 1) }}%<br>
                    <strong>Forecast Periods:</strong> {{ $forecast['forecast_periods'] }} days
                </p>
            @endif
        </div>
    @endif

    {{-- Recommendations Section --}}
    @if(isset($data['optimization_recommendations']))
        <div class="section">
            <h2>Optimization Recommendations</h2>
            
            @if(isset($data['optimization_recommendations']['priority_recommendations']))
                <div class="recommendations">
                    <h4>Priority Recommendations</h4>
                    <ul>
                        @foreach(array_slice($data['optimization_recommendations']['priority_recommendations'], 0, 5) as $recommendation)
                            <li>{{ $recommendation }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(isset($data['optimization_recommendations']['expected_savings']))
                <h3>Expected Savings</h3>
                @php $savings = $data['optimization_recommendations']['expected_savings']; @endphp
                <div class="metric-card">
                    <div class="metric-value">${{ number_format($savings['monthly_savings'] ?? 0, 2) }}</div>
                    <div class="metric-label">Monthly Savings</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($savings['percentage_reduction'] ?? 0, 1) }}%</div>
                    <div class="metric-label">Cost Reduction</div>
                </div>
            @endif
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <p>
            This report was generated automatically by the AI Analytics System.<br>
            Report ID: {{ $data['metadata']['report_id'] ?? 'N/A' }} | 
            Generated on {{ \Carbon\Carbon::parse($data['metadata']['generated_at'])->format('M j, Y \a\t g:i A T') }}
        </p>
    </div>
</body>
</html>
