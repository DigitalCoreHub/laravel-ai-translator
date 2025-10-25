<div class="p-6 bg-white shadow rounded-lg" wire:poll.2s="refreshQueueStatus">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-900">Queue Progress</h2>
        @if($hasActiveJobs)
            <button 
                wire:click="clearCompleted" 
                class="px-3 py-1 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded hover:bg-gray-50"
            >
                Clear Completed
            </button>
        @endif
    </div>

    @if($hasActiveJobs)
        <div class="space-y-4">
            <!-- Progress Bar -->
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="font-medium text-gray-700">{{ $statusMessage }}</span>
                    <span class="text-gray-500">{{ $completionPercentage }}%</span>
                </div>
                
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div 
                        class="h-2.5 rounded-full transition-all duration-300 
                               @if($statusColor === 'green') bg-green-500
                               @elseif($statusColor === 'blue') bg-blue-500
                               @elseif($statusColor === 'red') bg-red-500
                               @else bg-gray-400 @endif"
                        style="width: {{ $completionPercentage }}%"
                    ></div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-3 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ $pending }}</div>
                    <div class="text-sm text-blue-800">Pending</div>
                </div>
                
                <div class="text-center p-3 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ $completed }}</div>
                    <div class="text-sm text-green-800">Completed</div>
                </div>
                
                <div class="text-center p-3 bg-red-50 rounded-lg">
                    <div class="text-2xl font-bold text-red-600">{{ $failed }}</div>
                    <div class="text-sm text-red-800">Failed</div>
                </div>
                
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-gray-600">{{ $total }}</div>
                    <div class="text-sm text-gray-800">Total</div>
                </div>
            </div>

            <!-- Recent Jobs -->
            @if(!empty($recentJobs))
                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Recent Jobs</h3>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @foreach($recentJobs as $job)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg text-sm">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">{{ $job['file'] ?? 'Unknown' }}</div>
                                    <div class="text-gray-500">
                                        {{ $job['from'] ?? 'en' }} → {{ $job['to'] ?? 'tr' }} 
                                        ({{ $job['provider'] ?? 'unknown' }})
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="flex items-center space-x-2">
                                        @if(($job['status'] ?? '') === 'completed')
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                ✓ Completed
                                            </span>
                                        @elseif(($job['status'] ?? '') === 'failed')
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                ✗ Failed
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                ⏳ Processing
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        @if(isset($job['translated']))
                                            {{ $job['translated'] }} translations
                                        @endif
                                        @if(isset($job['duration']))
                                            ({{ $job['duration'] }}s)
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="text-center py-8">
            <div class="text-gray-400 text-4xl mb-4">📋</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Active Jobs</h3>
            <p class="text-gray-500">Translation jobs will appear here when they are queued.</p>
        </div>
    @endif
</div>