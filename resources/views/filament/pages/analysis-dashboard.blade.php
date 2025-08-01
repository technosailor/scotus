<x-filament-panels::page>
    @php
        $data = $this->getViewData();
    @endphp

    @if(isset($data['error']))
        <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Error Loading Dashboard</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p>{{ $data['error'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        @if(!empty($data['redis_stats']))
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Opinions</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($data['redis_stats']['total_opinions']) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Dissents</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($data['redis_stats']['dissenting_opinions']) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Concurrences</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($data['redis_stats']['concurring_opinions']) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Majority Opinions</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($data['redis_stats']['majority_opinions']) }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Analysis Status -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Analysis Status</h3>
            </div>
            <div class="p-6">
                @if($data['analysis_status'] === 'available')
                    <div class="flex items-center text-green-600">
                        <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium">Analysis data available</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">LLM analysis has been completed for sample opinions. View detailed results in the Opinion Analysis section.</p>
                @else
                    <div class="flex items-center text-yellow-600">
                        <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium">Analysis pending</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">No LLM analysis data found. Click "Run Full Analysis" to start analyzing Supreme Court opinions.</p>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Quick Actions</h3>
            </div>
            <div class="p-6 space-y-4">
                <button class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors" 
                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'run-analysis' } }))">
                    <svg class="h-5 w-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Analyze Sample Opinions
                </button>
                
                <a href="{{ route('filament.admin.resources.opinion-analysis.index') }}" 
                   class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors inline-block text-center">
                    <svg class="h-5 w-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    View Analysis Results
                </a>
            </div>
        </div>
    </div>

    <!-- Sentiment Distribution -->
    @if(!empty($data['sentiment_distribution']))
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Sentiment Distribution by Opinion Type</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($data['sentiment_distribution'] as $type => $data_item)
                        <div class="border rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-2">{{ ucfirst($type) }}</h4>
                            <p class="text-sm text-gray-600 mb-3">Analyzed: {{ $data_item['total_analyzed'] }}</p>
                            
                            @if(!empty($data_item['sentiments']))
                                <div class="space-y-2">
                                    @foreach($data_item['sentiments'] as $sentiment => $count)
                                        @php
                                            $percentage = round(($count / $data_item['total_analyzed']) * 100, 1);
                                            $color = match($sentiment) {
                                                'positive' => 'bg-green-500',
                                                'negative' => 'bg-red-500',
                                                'neutral' => 'bg-gray-500',
                                                default => 'bg-blue-500'
                                            };
                                        @endphp
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="capitalize">{{ $sentiment }}</span>
                                            <span>{{ $percentage }}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="{{ $color }} h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Thematic Analysis -->
    @if(!empty($data['thematic_analysis']))
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Sample Thematic Analysis</h3>
                <p class="text-sm text-gray-600">{{ $data['thematic_analysis']['theme'] ?? 'General Analysis' }}</p>
            </div>
            <div class="p-6">
                <div class="prose max-w-none">
                    <p class="text-gray-700 leading-relaxed">
                        {{ $data['thematic_analysis']['analysis'] ?? 'No thematic analysis available yet.' }}
                    </p>
                </div>
                
                @if(!empty($data['thematic_analysis']['key_insights']))
                    <div class="mt-6">
                        <h4 class="font-medium text-gray-900 mb-3">Key Insights</h4>
                        <ul class="space-y-2">
                            @foreach($data['thematic_analysis']['key_insights'] as $insight)
                                <li class="flex items-start">
                                    <svg class="h-5 w-5 text-blue-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-gray-700">{{ $insight }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <div class="mt-6 flex items-center justify-between text-sm text-gray-500">
                    <span>Time Period: {{ $data['thematic_analysis']['time_period'] ?? 'N/A' }}</span>
                    <span>Cases Analyzed: {{ $data['thematic_analysis']['cases_analyzed'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>