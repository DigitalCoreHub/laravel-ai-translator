<div class="p-6 bg-white shadow rounded-lg" @if($autoRefresh) wire:poll.5s="refreshLogs" @endif>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-gray-900">Watch Logs</h2>
        <div class="flex items-center space-x-4">
            <button 
                wire:click="toggleAutoRefresh"
                class="px-3 py-1 text-sm {{ $autoRefresh ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }} rounded-md hover:bg-opacity-80"
            >
                {{ $autoRefresh ? 'Auto Refresh ON' : 'Auto Refresh OFF' }}
            </button>
            <button 
                wire:click="refreshLogs"
                class="px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-md hover:bg-blue-200"
            >
                Refresh
            </button>
            <button 
                wire:click="clearLogs"
                wire:confirm="Are you sure you want to clear all logs?"
                class="px-3 py-1 text-sm bg-red-100 text-red-800 rounded-md hover:bg-red-200"
            >
                Clear Logs
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6">
        <div class="flex items-center space-x-4">
            <label class="text-sm font-medium text-gray-700">Filter:</label>
            <select 
                wire:model="filter"
                class="px-3 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                <option value="all">All Logs</option>
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="error">Error</option>
            </select>
        </div>
    </div>

    <!-- Logs Table -->
    @if(!empty($paginatedLogs))
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Timestamp
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Level
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Message
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Context
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($paginatedLogs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $log['timestamp'] }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $this->getLogLevelColor($log['level']) }}">
                                    {{ strtoupper($log['level']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $log['message'] }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                @if(!empty($log['context']))
                                    <div class="max-w-xs truncate" title="{{ $this->formatContext($log['context']) }}">
                                        {{ $this->formatContext($log['context']) }}
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($totalPages > 1)
            <div class="mt-6 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing {{ (($page - 1) * $perPage) + 1 }} to {{ min($page * $perPage, count($filteredLogs)) }} of {{ count($filteredLogs) }} logs
                </div>
                
                <div class="flex items-center space-x-2">
                    <button 
                        wire:click="previousPage"
                        @disabled($page <= 1)
                        class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Previous
                    </button>
                    
                    <div class="flex items-center space-x-1">
                        @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
                            <button 
                                wire:click="goToPage({{ $i }})"
                                class="px-3 py-1 text-sm border rounded-md {{ $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50' }}"
                            >
                                {{ $i }}
                            </button>
                        @endfor
                    </div>
                    
                    <button 
                        wire:click="nextPage"
                        @disabled($page >= $totalPages)
                        class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Next
                    </button>
                </div>
            </div>
        @endif
    @else
        <div class="text-center py-8">
            <div class="text-gray-400 text-4xl mb-4">📋</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Logs Found</h3>
            <p class="text-gray-500">
                @if($filter !== 'all')
                    No {{ $filter }} logs found. Try changing the filter.
                @else
                    No watch logs available. Start the watcher to see logs here.
                @endif
            </p>
        </div>
    @endif
</div>