<?php

namespace JTD\LaravelAI\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Spending Chart Component
 *
 * Displays spending trends and cost breakdowns with interactive charts
 * for budget dashboard visualization.
 */
class SpendingChart extends Component
{
    /**
     * Chart type (line, bar, pie, donut).
     */
    public string $type;

    /**
     * Chart data.
     */
    public array $data;

    /**
     * Chart title.
     */
    public string $title;

    /**
     * Chart height.
     */
    public string $height;

    /**
     * Chart configuration options.
     */
    public array $options;

    /**
     * Additional CSS classes.
     */
    public string $class;

    /**
     * Create a new component instance.
     *
     * @param  string  $type  Chart type
     * @param  array  $data  Chart data
     * @param  string  $title  Chart title
     * @param  string  $height  Chart height
     * @param  array  $options  Chart options
     * @param  string  $class  Additional CSS classes
     */
    public function __construct(
        string $type = 'line',
        array $data = [],
        string $title = '',
        string $height = '300px',
        array $options = [],
        string $class = ''
    ) {
        $this->type = $type;
        $this->data = $data;
        $this->title = $title;
        $this->height = $height;
        $this->options = array_merge($this->getDefaultOptions(), $options);
        $this->class = $class;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('laravel-ai::components.spending-chart');
    }

    /**
     * Get default chart options.
     *
     * @return array Default options
     */
    protected function getDefaultOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => $this->getDefaultScales(),
            'animation' => [
                'duration' => 1000,
                'easing' => 'easeInOutQuart',
            ],
        ];
    }

    /**
     * Get default scales configuration.
     *
     * @return array Default scales
     */
    protected function getDefaultScales(): array
    {
        if (in_array($this->type, ['pie', 'donut'])) {
            return [];
        }

        return [
            'x' => [
                'display' => true,
                'title' => [
                    'display' => true,
                    'text' => 'Time Period',
                ],
                'grid' => [
                    'display' => true,
                    'color' => 'rgba(0, 0, 0, 0.1)',
                ],
            ],
            'y' => [
                'display' => true,
                'title' => [
                    'display' => true,
                    'text' => 'Cost ($)',
                ],
                'grid' => [
                    'display' => true,
                    'color' => 'rgba(0, 0, 0, 0.1)',
                ],
                'ticks' => [
                    'callback' => 'function(value) { return "$" + value.toFixed(2); }',
                ],
            ],
        ];
    }

    /**
     * Get chart configuration as JSON.
     *
     * @return string Chart configuration JSON
     */
    public function getChartConfig(): string
    {
        $config = [
            'type' => $this->type,
            'data' => $this->data,
            'options' => $this->options,
        ];

        return json_encode($config, JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * Get unique chart ID.
     *
     * @return string Chart ID
     */
    public function getChartId(): string
    {
        return 'spending-chart-' . uniqid();
    }

    /**
     * Get formatted data summary.
     *
     * @return array Data summary
     */
    public function getDataSummary(): array
    {
        if (empty($this->data['datasets'])) {
            return [
                'total_cost' => 0,
                'data_points' => 0,
                'date_range' => 'No data',
            ];
        }

        $dataset = $this->data['datasets'][0];
        $values = $dataset['data'] ?? [];
        $labels = $this->data['labels'] ?? [];

        return [
            'total_cost' => array_sum($values),
            'data_points' => count($values),
            'date_range' => $this->getDateRange($labels),
            'average_cost' => count($values) > 0 ? array_sum($values) / count($values) : 0,
            'max_cost' => count($values) > 0 ? max($values) : 0,
            'min_cost' => count($values) > 0 ? min($values) : 0,
        ];
    }

    /**
     * Get date range from labels.
     *
     * @param  array  $labels  Chart labels
     * @return string Date range
     */
    protected function getDateRange(array $labels): string
    {
        if (empty($labels)) {
            return 'No data';
        }

        if (count($labels) === 1) {
            return $labels[0];
        }

        return $labels[0] . ' - ' . end($labels);
    }

    /**
     * Get chart color palette.
     *
     * @return array Color palette
     */
    public function getColorPalette(): array
    {
        return [
            'primary' => 'rgba(59, 130, 246, 0.8)',
            'secondary' => 'rgba(16, 185, 129, 0.8)',
            'accent' => 'rgba(245, 158, 11, 0.8)',
            'warning' => 'rgba(239, 68, 68, 0.8)',
            'info' => 'rgba(139, 92, 246, 0.8)',
            'success' => 'rgba(34, 197, 94, 0.8)',
            'gray' => 'rgba(107, 114, 128, 0.8)',
        ];
    }

    /**
     * Get chart insights.
     *
     * @return array Chart insights
     */
    public function getInsights(): array
    {
        $summary = $this->getDataSummary();
        $insights = [];

        if ($summary['data_points'] === 0) {
            return ['No data available for analysis'];
        }

        // Trend analysis
        if ($this->type === 'line' && ! empty($this->data['datasets'][0]['data'])) {
            $data = $this->data['datasets'][0]['data'];
            $trend = $this->analyzeTrend($data);
            $insights[] = "Spending trend: {$trend}";
        }

        // Cost analysis
        if ($summary['total_cost'] > 0) {
            $insights[] = 'Total spending: $' . number_format($summary['total_cost'], 2);
            $insights[] = 'Average per period: $' . number_format($summary['average_cost'], 2);

            if ($summary['max_cost'] > $summary['average_cost'] * 2) {
                $insights[] = 'Peak spending detected: $' . number_format($summary['max_cost'], 2);
            }
        }

        return $insights;
    }

    /**
     * Analyze spending trend.
     *
     * @param  array  $data  Data points
     * @return string Trend description
     */
    protected function analyzeTrend(array $data): string
    {
        if (count($data) < 2) {
            return 'insufficient data';
        }

        $first = array_slice($data, 0, ceil(count($data) / 3));
        $last = array_slice($data, -ceil(count($data) / 3));

        $firstAvg = array_sum($first) / count($first);
        $lastAvg = array_sum($last) / count($last);

        $change = (($lastAvg - $firstAvg) / $firstAvg) * 100;

        if (abs($change) < 5) {
            return 'stable';
        }

        if ($change > 0) {
            return $change > 20 ? 'rapidly increasing' : 'increasing';
        }

        return $change < -20 ? 'rapidly decreasing' : 'decreasing';
    }

    /**
     * Check if chart has data.
     *
     * @return bool Whether chart has data
     */
    public function hasData(): bool
    {
        return ! empty($this->data['datasets']) &&
               ! empty($this->data['datasets'][0]['data']) &&
               array_sum($this->data['datasets'][0]['data']) > 0;
    }

    /**
     * Get empty state message.
     *
     * @return string Empty state message
     */
    public function getEmptyStateMessage(): string
    {
        return match ($this->type) {
            'line' => 'No spending trends to display. Start using AI services to see your spending patterns.',
            'bar' => 'No spending data available. Your usage will appear here once you start making AI requests.',
            'pie', 'donut' => 'No cost breakdown available. Spending distribution will be shown after AI usage.',
            default => 'No data available to display.',
        };
    }

    /**
     * Get chart export options.
     *
     * @return array Export options
     */
    public function getExportOptions(): array
    {
        return [
            'png' => 'Export as PNG',
            'jpg' => 'Export as JPG',
            'pdf' => 'Export as PDF',
            'csv' => 'Export data as CSV',
        ];
    }
}
