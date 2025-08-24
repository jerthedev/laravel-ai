{{-- Budget Status Card Component --}}
<div class="budget-status-card {{ $getStatusColorClass() }} {{ $class }} border rounded-lg p-6 shadow-sm">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-2">
            <span class="text-lg">{{ $getStatusIcon() }}</span>
            <h3 class="text-lg font-semibold">{{ $getBudgetTypeDisplay() }}</h3>
        </div>
        
        @if($needsAttention())
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                {{ ucfirst($getUrgencyLevel()) }}
            </span>
        @endif
    </div>

    {{-- Budget Status --}}
    @if($isActive)
        {{-- Spending Overview --}}
        <div class="mb-4">
            <div class="flex justify-between items-baseline mb-2">
                <span class="text-sm font-medium">Spent</span>
                <span class="text-lg font-bold">{{ $getFormattedSpent() }}</span>
            </div>
            
            <div class="flex justify-between items-baseline mb-3">
                <span class="text-sm text-gray-600">of {{ $getFormattedLimit() }}</span>
                <span class="text-sm font-medium">{{ $getFormattedRemaining() }}</span>
            </div>

            {{-- Progress Bar --}}
            @if($limit)
                <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                    <div class="{{ $getProgressBarColor() }} h-2.5 rounded-full transition-all duration-300" 
                         style="width: {{ min($percentageUsed, 100) }}%"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-600">
                    <span>0%</span>
                    <span class="font-medium">{{ $getFormattedPercentage() }}</span>
                    <span>100%</span>
                </div>
            @endif
        </div>

        {{-- Status Message --}}
        <div class="mb-4">
            <p class="text-sm {{ $status === 'exceeded' ? 'font-semibold' : '' }}">
                {{ $getStatusMessage() }}
            </p>
        </div>

        {{-- Reset Information --}}
        @if($getResetDateDisplay())
            <div class="mb-4 p-3 bg-white bg-opacity-50 rounded-md">
                <div class="flex items-center space-x-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm text-gray-700">{{ $getResetDateDisplay() }}</span>
                </div>
            </div>
        @endif

        {{-- Recommended Actions --}}
        @if($needsAttention())
            <div class="border-t pt-4">
                <h4 class="text-sm font-medium mb-2">Recommended Actions:</h4>
                <ul class="text-xs space-y-1">
                    @foreach(array_slice($getRecommendedActions(), 0, 3) as $action)
                        <li class="flex items-start space-x-2">
                            <span class="text-gray-400 mt-0.5">•</span>
                            <span>{{ $action }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

    @else
        {{-- Inactive Budget --}}
        <div class="text-center py-8">
            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
            </svg>
            <p class="text-sm text-gray-600 mb-4">{{ $getStatusMessage() }}</p>
            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                Configure Budget
            </button>
        </div>
    @endif

    {{-- Real-time Update Indicator --}}
    <div class="flex items-center justify-between mt-4 pt-4 border-t border-opacity-20">
        <div class="flex items-center space-x-2 text-xs text-gray-500">
            <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
            <span>Live updates</span>
        </div>
        
        <button class="text-xs text-gray-500 hover:text-gray-700 transition-colors duration-200" 
                onclick="refreshBudgetCard('{{ $budgetType }}')">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
        </button>
    </div>
</div>

{{-- JavaScript for real-time updates --}}
<script>
function refreshBudgetCard(budgetType) {
    // This would integrate with your frontend framework (Vue, React, etc.)
    // to refresh the budget card data via AJAX
    console.log('Refreshing budget card:', budgetType);
    
    // Example implementation:
    // fetch(`/api/budget/status?budget_type=${budgetType}`)
    //     .then(response => response.json())
    //     .then(data => updateBudgetCard(budgetType, data));
}

// Auto-refresh every 30 seconds for active budgets
@if($isActive && $needsAttention())
    setInterval(() => {
        refreshBudgetCard('{{ $budgetType }}');
    }, 30000);
@endif
</script>

{{-- Styles for animations and transitions --}}
<style>
.budget-status-card {
    transition: all 0.3s ease;
}

.budget-status-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

@keyframes pulse-slow {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse-slow {
    animation: pulse-slow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
