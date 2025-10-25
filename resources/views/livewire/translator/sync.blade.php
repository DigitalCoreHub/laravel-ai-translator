<div class="p-6 bg-white shadow rounded-lg">
    <h2 class="text-xl font-semibold text-gray-900 mb-6">Sync Translation Files</h2>

    <form wire:submit.prevent="startSync">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Source Language -->
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700 mb-2">
                    Source Language
                </label>
                <select 
                    wire:model="from" 
                    id="from"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                >
                    @foreach($availableLocales as $locale)
                        <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                    @endforeach
                </select>
                @error('from') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <!-- Target Language -->
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700 mb-2">
                    Target Language
                </label>
                <select 
                    wire:model="to" 
                    id="to"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                >
                    @foreach($availableLocales as $locale)
                        <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                    @endforeach
                </select>
                @error('to') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <!-- Provider -->
            <div>
                <label for="provider" class="block text-sm font-medium text-gray-700 mb-2">
                    Translation Provider
                </label>
                <select 
                    wire:model="provider" 
                    id="provider"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                >
                    @foreach($availableProviders as $providerName)
                        <option value="{{ $providerName }}">{{ ucfirst($providerName) }}</option>
                    @endforeach
                </select>
                @error('provider') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <!-- Processing Mode -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Processing Mode
                </label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input 
                            type="radio" 
                            wire:model="useQueue" 
                            value="1"
                            class="mr-2 text-blue-600 focus:ring-blue-500"
                        >
                        <span class="text-sm text-gray-700">Queue (Background Processing)</span>
                    </label>
                    <label class="flex items-center">
                        <input 
                            type="radio" 
                            wire:model="useQueue" 
                            value="0"
                            class="mr-2 text-blue-600 focus:ring-blue-500"
                        >
                        <span class="text-sm text-gray-700">Direct (Immediate Processing)</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Options -->
        <div class="mb-6">
            <div class="flex items-center space-x-4">
                <label class="flex items-center">
                    <input 
                        type="checkbox" 
                        wire:model="force"
                        class="mr-2 text-blue-600 focus:ring-blue-500"
                    >
                    <span class="text-sm text-gray-700">Force retranslation of existing translations</span>
                </label>
            </div>
        </div>

        <!-- File List -->
        @if(!empty($files))
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-3">
                    Files to Process ({{ count($files) }})
                </h3>
                <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-md">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    File
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Size
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Modified
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($files as $file)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        {{ $file['name'] }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        {{ number_format($file['size'] / 1024, 1) }} KB
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        {{ date('Y-m-d H:i:s', $file['modified']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Progress Bar -->
        @if($isProcessing)
            <div class="mb-6">
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-medium text-gray-700">{{ $status }}</span>
                    <span class="text-gray-500">{{ $progressPercentage }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div 
                        class="h-2.5 bg-blue-500 rounded-full transition-all duration-300"
                        style="width: {{ $progressPercentage }}%"
                    ></div>
                </div>
            </div>
        @endif

        <!-- Action Buttons -->
        <div class="flex items-center space-x-4">
            @if($canStartSync)
                <button 
                    type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    Start Sync
                </button>
            @elseif($isProcessing)
                <button 
                    type="button"
                    wire:click="cancelSync"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                >
                    Cancel
                </button>
            @endif

            <button 
                type="button"
                wire:click="refreshSync"
                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
            >
                Refresh
            </button>
        </div>

        <!-- Status Message -->
        @if($status)
            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                <p class="text-sm text-blue-800">{{ $status }}</p>
            </div>
        @endif
    </form>
</div>