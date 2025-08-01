<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supreme Court Data Visualization</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- D3.js -->
    <script src="https://d3js.org/d3.v7.min.js"></script>
    
    <!-- Chart.js for additional charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Supreme Court Historical Data Analysis</h1>
                    <p class="text-gray-600">Interactive visualization of Supreme Court cases, justices, and decisions</p>
                </div>
                <div class="flex space-x-2">
                    <a href="/admin" class="px-3 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 text-sm">Admin</a>
                    <a href="/justices" class="px-3 py-2 bg-amber-500 text-white rounded-md hover:bg-amber-600 text-sm">Justices</a>
                    <a href="/cases" class="px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 text-sm">Cases</a>
                    <a href="/opinions" class="px-3 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 text-sm">Opinions</a>
                    <a href="/presidents" class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 text-sm">Presidents</a>
                    <a href="/terms" class="px-3 py-2 bg-purple-500 text-white rounded-md hover:bg-purple-600 text-sm">Terms</a>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ $stats['total_cases'] }}</div>
                    <div class="text-sm text-gray-600">Cases</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ $stats['total_justices'] }}</div>
                    <div class="text-sm text-gray-600">Justices</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">{{ $stats['total_terms'] }}</div>
                    <div class="text-sm text-gray-600">Terms</div>
                </div>
                <div class="bg-orange-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-orange-600">{{ $stats['total_opinions'] }}</div>
                    <div class="text-sm text-gray-600">Opinions</div>
                </div>
            </div>
        </div>

        <!-- Precedential Analysis Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div id="precedential-cases">
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-2 text-gray-600">Loading precedential analysis...</p>
                </div>
            </div>
        </div>

        <!-- Justice Language Analysis Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div id="justice-language">
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div>
                    <p class="mt-2 text-gray-600">Loading justice language patterns...</p>
                </div>
            </div>
        </div>

        <!-- Topic Trends Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div id="topic-trends">
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
                    <p class="mt-2 text-gray-600">Loading topic trends...</p>
                </div>
            </div>
        </div>

        <!-- Interactive Heatmaps Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Interactive Analysis Heatmaps</h2>
            
            <!-- Justice × Topic Heatmap -->
            <div class="mb-8">
                <div id="justice-topic-heatmap" class="w-full overflow-x-auto"></div>
            </div>
            
            <!-- Topic × Time Period Heatmap -->
            <div class="mb-8">
                <div id="topic-time-heatmap" class="w-full overflow-x-auto"></div>
            </div>
            
            <!-- Precedential Cases × Time Heatmap -->
            <div class="mb-8">
                <div id="precedential-time-heatmap" class="w-full overflow-x-auto"></div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Search and Chat -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Chat Interface -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4">Ask About Cases</h2>
                    <div id="chat-container" class="space-y-4">
                        <div id="chat-messages" class="h-64 overflow-y-auto border rounded p-4 bg-gray-50">
                            <div class="text-gray-500 text-sm">Ask me about Supreme Court cases, justices, or legal topics...</div>
                        </div>
                        <div class="flex">
                            <input type="text" id="chat-input" 
                                   class="flex-1 px-3 py-2 border rounded-l-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="e.g., Tell me about civil rights cases">
                            <button id="chat-send" 
                                    class="px-4 py-2 bg-blue-500 text-white rounded-r-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Send
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Search Filters -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4">Search & Filter</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search Cases</label>
                            <input type="text" id="search-input" 
                                   class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Case name or keywords">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Term Year</label>
                            <select id="term-filter" 
                                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Terms</option>
                                <option value="1955">1955</option>
                                <option value="1956">1956</option>
                                <option value="1957">1957</option>
                                <option value="1958">1958</option>
                                <option value="1959">1959</option>
                                <option value="1960">1960</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sentiment Range</label>
                            <div class="flex space-x-2">
                                <input type="range" id="sentiment-min" min="-1" max="1" step="0.1" value="-1" 
                                       class="flex-1">
                                <input type="range" id="sentiment-max" min="-1" max="1" step="0.1" value="1" 
                                       class="flex-1">
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>Negative</span>
                                <span>Neutral</span>
                                <span>Positive</span>
                            </div>
                        </div>
                        
                        <button id="search-button" 
                                class="w-full px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500">
                            Search Cases
                        </button>
                    </div>
                </div>

                <!-- Search Results -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4">Search Results</h2>
                    <div id="search-results" class="space-y-2">
                        <div class="text-gray-500 text-sm">Use the search above to find cases</div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Visualizations -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Timeline Visualization -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4">Supreme Court Cases Per Term</h2>
                    <div class="flex space-x-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Year</label>
                            <input type="number" id="cases-start" value="1793" min="1793" max="2025"
                                   class="px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Year</label>
                            <input type="number" id="cases-end" value="2025" min="1793" max="2025"
                                   class="px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex items-end">
                            <button id="update-cases-chart" 
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Update Chart
                            </button>
                        </div>
                    </div>
                    <div id="cases-per-term-chart" class="w-full h-80"></div>
                </div>

                <!-- Justice Opinion Statistics -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4">Justice Opinion Statistics</h2>
                    <div class="flex space-x-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Year</label>
                            <input type="number" id="justice-stats-start" value="1950" min="1793" max="2025"
                                   class="px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Year</label>
                            <input type="number" id="justice-stats-end" value="2025" min="1793" max="2025"
                                   class="px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex items-end">
                            <button id="update-justice-stats" 
                                    class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500">
                                Update Chart
                            </button>
                        </div>
                    </div>
                    <div id="justice-opinion-stats" class="w-full h-96"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global state
        let currentCases = [];
        let timelineChart = null;
        let networkSvg = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
            loadInitialData();
            loadPrecedentialAnalysis();
        });

        function initializeEventListeners() {
            // Chat functionality
            document.getElementById('chat-send').addEventListener('click', sendChatMessage);
            document.getElementById('chat-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') sendChatMessage();
            });

            // Search functionality
            document.getElementById('search-button').addEventListener('click', performSearch);
            document.getElementById('search-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') performSearch();
            });

            // Chart controls
            document.getElementById('update-cases-chart').addEventListener('click', updateCasesChart);
            document.getElementById('update-justice-stats').addEventListener('click', updateJusticeStats);
        }

        async function loadInitialData() {
            await updateCasesChart();
            await updateJusticeStats();
        }

        // Chat functionality
        async function sendChatMessage() {
            const input = document.getElementById('chat-input');
            const message = input.value.trim();
            if (!message) return;

            const messagesContainer = document.getElementById('chat-messages');
            
            // Add user message
            addChatMessage(message, 'user');
            input.value = '';

            try {
                const response = await fetch('/api/supreme-court/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ message })
                });

                const data = await response.json();
                addChatMessage(data.response, 'assistant');
                
                if (data.suggestions && data.suggestions.length > 0) {
                    addChatSuggestions(data.suggestions);
                }
            } catch (error) {
                addChatMessage('Sorry, I encountered an error. Please try again.', 'assistant');
            }
        }

        function addChatMessage(message, type) {
            const messagesContainer = document.getElementById('chat-messages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `mb-2 ${type === 'user' ? 'text-right' : 'text-left'}`;
            
            const bubble = document.createElement('div');
            bubble.className = `inline-block px-3 py-2 rounded-lg max-w-xs ${
                type === 'user' 
                    ? 'bg-blue-500 text-white' 
                    : 'bg-gray-200 text-gray-800'
            }`;
            bubble.textContent = message;
            
            messageDiv.appendChild(bubble);
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function addChatSuggestions(suggestions) {
            const messagesContainer = document.getElementById('chat-messages');
            const suggestionsDiv = document.createElement('div');
            suggestionsDiv.className = 'mb-2 text-left';
            
            const title = document.createElement('div');
            title.className = 'text-xs text-gray-500 mb-1';
            title.textContent = 'Suggestions:';
            suggestionsDiv.appendChild(title);
            
            suggestions.forEach(suggestion => {
                const suggestionButton = document.createElement('button');
                suggestionButton.className = 'block text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded mb-1 hover:bg-blue-200';
                suggestionButton.textContent = suggestion;
                suggestionButton.addEventListener('click', () => {
                    document.getElementById('chat-input').value = suggestion;
                });
                suggestionsDiv.appendChild(suggestionButton);
            });
            
            messagesContainer.appendChild(suggestionsDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Search functionality
        async function performSearch() {
            const query = document.getElementById('search-input').value;
            const term = document.getElementById('term-filter').value;
            const sentimentMin = document.getElementById('sentiment-min').value;
            const sentimentMax = document.getElementById('sentiment-max').value;

            const params = new URLSearchParams();
            if (query) params.append('q', query);
            
            const filters = {};
            if (term) filters.term = term;
            if (sentimentMin !== '-1') filters.sentiment_min = sentimentMin;
            if (sentimentMax !== '1') filters.sentiment_max = sentimentMax;
            
            if (Object.keys(filters).length > 0) {
                params.append('filters', JSON.stringify(filters));
            }

            try {
                const response = await fetch(`/api/supreme-court/search?${params}`);
                const data = await response.json();
                displaySearchResults(data.cases);
                currentCases = data.cases;
            } catch (error) {
                console.error('Search failed:', error);
                document.getElementById('search-results').innerHTML = 
                    '<div class="text-red-500 text-sm">Search failed. Please try again.</div>';
            }
        }

        function displaySearchResults(cases) {
            const container = document.getElementById('search-results');
            
            if (cases.length === 0) {
                container.innerHTML = '<div class="text-gray-500 text-sm">No cases found</div>';
                return;
            }

            container.innerHTML = cases.map(case_ => `
                <div class="border rounded p-3 hover:bg-gray-50 cursor-pointer case-result" data-case-id="${case_.id}">
                    <div class="font-medium text-gray-800">${case_.name}</div>
                    <div class="text-sm text-gray-600">
                        ${case_.decision_date} • Term ${case_.term}
                        ${case_.sentiment_score ? ` • Sentiment: ${case_.sentiment_score.toFixed(2)}` : ''}
                    </div>
                    ${case_.summary ? `<div class="text-xs text-gray-500 mt-1">${case_.summary}</div>` : ''}
                </div>
            `).join('');

            // Add click handlers for case results
            container.querySelectorAll('.case-result').forEach(element => {
                element.addEventListener('click', () => {
                    const caseId = element.dataset.caseId;
                    showCaseDetails(caseId);
                });
            });
        }

        function showCaseDetails(caseId) {
            const case_ = currentCases.find(c => c.id == caseId);
            if (!case_) return;

            alert(`Case: ${case_.name}\nDate: ${case_.decision_date}\nDocket: ${case_.docket_number}\nSentiment: ${case_.sentiment_score}\nSummary: ${case_.summary || 'No summary available'}`);
        }

        // Timeline visualization
        async function updateCasesChart() {
            const startYear = document.getElementById('cases-start').value;
            const endYear = document.getElementById('cases-end').value;

            try {
                const response = await fetch(`/api/supreme-court/cases-per-term?start_year=${startYear}&end_year=${endYear}`);
                const data = await response.json();
                createCasesPerTermChart(data);
            } catch (error) {
                console.error('Cases chart update failed:', error);
            }
        }

        function createCasesPerTermChart(data) {
            const container = document.getElementById('cases-per-term-chart');
            container.innerHTML = '';

            if (data.length === 0) {
                container.innerHTML = '<div class="text-gray-500 text-center py-8">No data available for selected range</div>';
                return;
            }

            const margin = { top: 20, right: 30, bottom: 60, left: 60 };
            const width = container.offsetWidth - margin.left - margin.right;
            const height = 320 - margin.top - margin.bottom;

            const svg = d3.select(container)
                .append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom);

            const g = svg.append('g')
                .attr('transform', `translate(${margin.left},${margin.top})`);

            // Scales
            const xScale = d3.scaleBand()
                .domain(data.map(d => d.term))
                .range([0, width])
                .padding(0.1);

            const yScale = d3.scaleLinear()
                .domain([0, d3.max(data, d => d.count)])
                .range([height, 0]);

            // Axes
            g.append('g')
                .attr('transform', `translate(0,${height})`)
                .call(d3.axisBottom(xScale))
                .selectAll('text')
                .style('text-anchor', 'end')
                .attr('dx', '-.8em')
                .attr('dy', '.15em')
                .attr('transform', 'rotate(-45)');

            g.append('g')
                .call(d3.axisLeft(yScale));

            // Bars
            g.selectAll('.bar')
                .data(data)
                .enter().append('rect')
                .attr('class', 'bar')
                .attr('x', d => xScale(d.term))
                .attr('width', xScale.bandwidth())
                .attr('y', d => yScale(d.count))
                .attr('height', d => height - yScale(d.count))
                .attr('fill', '#3b82f6')
                .on('mouseover', function(event, d) {
                    d3.select(this).attr('fill', '#1d4ed8');
                    
                    const tooltip = d3.select('body').append('div')
                        .attr('class', 'tooltip')
                        .style('opacity', 0)
                        .style('position', 'absolute')
                        .style('background', 'rgba(0, 0, 0, 0.8)')
                        .style('color', 'white')
                        .style('padding', '8px')
                        .style('border-radius', '4px')
                        .style('font-size', '12px');

                    tooltip.transition()
                        .duration(200)
                        .style('opacity', .9);

                    tooltip.html(`${d.term_name}<br/>Cases: ${d.count}`)
                        .style('left', (event.pageX + 10) + 'px')
                        .style('top', (event.pageY - 28) + 'px');
                })
                .on('mouseout', function() {
                    d3.select(this).attr('fill', '#3b82f6');
                    d3.selectAll('.tooltip').remove();
                });

            // Labels
            g.append('text')
                .attr('transform', 'rotate(-90)')
                .attr('y', 0 - margin.left)
                .attr('x', 0 - (height / 2))
                .attr('dy', '1em')
                .style('text-anchor', 'middle')
                .text('Number of Cases');

            g.append('text')
                .attr('transform', `translate(${width / 2}, ${height + margin.bottom - 10})`)
                .style('text-anchor', 'middle')
                .text('Supreme Court Term');
        }

        // Justice opinion statistics visualization
        async function updateJusticeStats() {
            const startYear = document.getElementById('justice-stats-start').value;
            const endYear = document.getElementById('justice-stats-end').value;

            try {
                const response = await fetch(`/api/supreme-court/justice-opinion-stats?start_year=${startYear}&end_year=${endYear}&limit=15`);
                const data = await response.json();
                createJusticeOpinionChart(data);
            } catch (error) {
                console.error('Justice stats update failed:', error);
            }
        }

        function createJusticeNetwork(data) {
            const container = document.getElementById('justice-network');
            container.innerHTML = '';

            if (data.nodes.length === 0) {
                container.innerHTML = '<div class="text-gray-500 text-center py-8">No data available for selected term</div>';
                return;
            }

            const width = container.offsetWidth;
            const height = 400;

            const svg = d3.select(container)
                .append('svg')
                .attr('width', width)
                .attr('height', height);

            const simulation = d3.forceSimulation(data.nodes)
                .force('link', d3.forceLink(data.links).id(d => d.id).distance(100))
                .force('charge', d3.forceManyBody().strength(-300))
                .force('center', d3.forceCenter(width / 2, height / 2));

            const link = svg.append('g')
                .attr('stroke', '#999')
                .attr('stroke-opacity', 0.6)
                .selectAll('line')
                .data(data.links)
                .join('line')
                .attr('stroke-width', d => Math.sqrt(d.strength * 5));

            const node = svg.append('g')
                .attr('stroke', '#fff')
                .attr('stroke-width', 1.5)
                .selectAll('circle')
                .data(data.nodes)
                .join('circle')
                .attr('r', d => 5 + d.opinion_count / 2)
                .attr('fill', d => d3.scaleSequential(d3.interpolateRdYlBu)(d.ideology_score + 5) / 10)
                .call(d3.drag()
                    .on('start', dragstarted)
                    .on('drag', dragged)
                    .on('end', dragended));

            node.append('title')
                .text(d => `${d.name}\nOpinions: ${d.opinion_count}\nIdeology: ${d.ideology_score.toFixed(2)}`);

            const label = svg.append('g')
                .selectAll('text')
                .data(data.nodes)
                .join('text')
                .text(d => d.name.split(' ').pop()) // Last name only
                .attr('font-size', '10px')
                .attr('text-anchor', 'middle')
                .attr('dy', '0.35em');

            simulation.on('tick', () => {
                link
                    .attr('x1', d => d.source.x)
                    .attr('y1', d => d.source.y)
                    .attr('x2', d => d.target.x)
                    .attr('y2', d => d.target.y);

                node
                    .attr('cx', d => d.x)
                    .attr('cy', d => d.y);

                label
                    .attr('x', d => d.x)
                    .attr('y', d => d.y);
            });

            function dragstarted(event, d) {
                if (!event.active) simulation.alphaTarget(0.3).restart();
                d.fx = d.x;
                d.fy = d.y;
            }

            function dragged(event, d) {
                d.fx = event.x;
                d.fy = event.y;
            }

            function dragended(event, d) {
                if (!event.active) simulation.alphaTarget(0);
                d.fx = null;
                d.fy = null;
            }

            networkSvg = svg;
        }

        function createJusticeOpinionChart(data) {
            const container = document.getElementById('justice-opinion-stats');
            container.innerHTML = '';

            if (data.length === 0) {
                container.innerHTML = '<div class="text-gray-500 text-center py-8">No data available for selected range</div>';
                return;
            }

            const margin = { top: 20, right: 80, bottom: 120, left: 60 };
            const width = container.offsetWidth - margin.left - margin.right;
            const height = 384 - margin.top - margin.bottom;

            const svg = d3.select(container)
                .append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom);

            const g = svg.append('g')
                .attr('transform', `translate(${margin.left},${margin.top})`);

            // Stack the data
            const keys = ['majority', 'concurring', 'dissent'];
            const stack = d3.stack().keys(keys);
            const stackedData = stack(data);

            // Scales
            const xScale = d3.scaleBand()
                .domain(data.map(d => d.name.split(' ').pop())) // Last names only
                .range([0, width])
                .padding(0.1);

            const yScale = d3.scaleLinear()
                .domain([0, d3.max(data, d => d.total)])
                .range([height, 0]);

            // Colors
            const colors = {
                majority: '#10b981',    // green
                concurring: '#f59e0b',  // yellow
                dissent: '#ef4444'      // red
            };

            // Axes
            g.append('g')
                .attr('transform', `translate(0,${height})`)
                .call(d3.axisBottom(xScale))
                .selectAll('text')
                .style('text-anchor', 'end')
                .attr('dx', '-.8em')
                .attr('dy', '.15em')
                .attr('transform', 'rotate(-45)');

            g.append('g')
                .call(d3.axisLeft(yScale));

            // Stacked bars
            g.selectAll('.stack')
                .data(stackedData)
                .enter()
                .append('g')
                .attr('class', 'stack')
                .attr('fill', d => colors[d.key])
                .selectAll('rect')
                .data(d => d)
                .enter()
                .append('rect')
                .attr('x', d => xScale(d.data.name.split(' ').pop()))
                .attr('y', d => yScale(d[1]))
                .attr('height', d => yScale(d[0]) - yScale(d[1]))
                .attr('width', xScale.bandwidth())
                .on('mouseover', function(event, d) {
                    const opinionType = d3.select(this.parentNode).datum().key;
                    const value = d[1] - d[0];
                    
                    d3.select(this)
                        .style('opacity', 0.8);
                    
                    const tooltip = d3.select('body').append('div')
                        .attr('class', 'tooltip')
                        .style('opacity', 0)
                        .style('position', 'absolute')
                        .style('background', 'rgba(0, 0, 0, 0.8)')
                        .style('color', 'white')
                        .style('padding', '8px')
                        .style('border-radius', '4px')
                        .style('font-size', '12px');

                    tooltip.transition()
                        .duration(200)
                        .style('opacity', .9);

                    tooltip.html(`${d.data.name}<br/>${opinionType.charAt(0).toUpperCase() + opinionType.slice(1)}: ${value}<br/>Total: ${d.data.total}`)
                        .style('left', (event.pageX + 10) + 'px')
                        .style('top', (event.pageY - 28) + 'px');
                })
                .on('mouseout', function() {
                    d3.select(this).style('opacity', 1);
                    d3.selectAll('.tooltip').remove();
                });

            // Legend
            const legend = g.selectAll('.legend')
                .data(keys)
                .enter()
                .append('g')
                .attr('class', 'legend')
                .attr('transform', (d, i) => `translate(${width + 10}, ${i * 20})`);

            legend.append('rect')
                .attr('x', 0)
                .attr('width', 18)
                .attr('height', 18)
                .style('fill', d => colors[d]);

            legend.append('text')
                .attr('x', 24)
                .attr('y', 9)
                .attr('dy', '.35em')
                .style('text-anchor', 'start')
                .style('font-size', '12px')
                .text(d => d.charAt(0).toUpperCase() + d.slice(1));

            // Labels
            g.append('text')
                .attr('transform', 'rotate(-90)')
                .attr('y', 0 - margin.left)
                .attr('x', 0 - (height / 2))
                .attr('dy', '1em')
                .style('text-anchor', 'middle')
                .text('Number of Opinions');

            g.append('text')
                .attr('transform', `translate(${width / 2}, ${height + margin.bottom - 10})`)
                .style('text-anchor', 'middle')
                .text('Supreme Court Justice');
        }

        // Load and display precedential analysis data
        function loadPrecedentialAnalysis() {
            console.log('Loading precedential analysis...');
            
            Promise.all([
                fetch('/api/supreme-court/precedential-analysis').then(r => r.json()),
                fetch('/api/supreme-court/justice-language-patterns').then(r => r.json()),
                fetch('/api/supreme-court/topic-trends').then(r => r.json()),
                fetch('/api/supreme-court/heatmap-data').then(r => r.json())
            ]).then(([precedential, language, topics, heatmap]) => {
                console.log('Precedential analysis data loaded:', { precedential, language, topics, heatmap });
                
                displayPrecedentialCases(precedential.major_cases);
                displayJusticeLanguagePatterns(language.comparative_analysis);
                displayTopicTrends(topics.trends);
                displayHeatmaps(heatmap);
                
                console.log('Precedential analysis display completed');
            }).catch(error => {
                console.error('Error loading precedential analysis:', error);
                
                // Show error messages in the UI
                document.getElementById('precedential-cases').innerHTML = '<p class="text-red-600">Error loading precedential cases</p>';
                document.getElementById('justice-language').innerHTML = '<p class="text-red-600">Error loading justice language analysis</p>';
                document.getElementById('topic-trends').innerHTML = '<p class="text-red-600">Error loading topic trends</p>';
                document.getElementById('heatmaps').innerHTML = '<p class="text-red-600">Error loading heatmaps</p>';
            });
        }

        function displayPrecedentialCases(majorCases) {
            const container = document.getElementById('precedential-cases');
            if (!container) return;

            let html = '<h3 class="text-lg font-semibold mb-4">Major Precedential Cases</h3>';
            html += '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

            Object.entries(majorCases).slice(0, 12).forEach(([caseName, data]) => {
                const year = data.decision_date ? new Date(data.decision_date).getFullYear() : 'Unknown';
                html += `
                    <div class="bg-white p-4 rounded-lg shadow hover:shadow-md transition-shadow">
                        <h4 class="font-semibold text-sm mb-2 text-gray-800">${caseName}</h4>
                        <div class="text-xs text-gray-600 space-y-1">
                            <div>Year: ${year}</div>
                            <div>Times Cited: ${data.times_cited}</div>
                            <div>Classification: <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded">${data.classification}</span></div>
                            <div>Precedential Score: ${data.precedential_score}</div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        function displayJusticeLanguagePatterns(analysis) {
            const container = document.getElementById('justice-language');
            if (!container) return;

            let html = '<h3 class="text-lg font-semibold mb-4">Justice Language Analysis</h3>';
            html += '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">';

            // Most Prolific Writers
            html += '<div class="bg-white p-4 rounded-lg shadow">';
            html += '<h4 class="font-medium mb-3 text-blue-600">Most Prolific Writers</h4>';
            Object.entries(analysis.most_prolific).slice(0, 5).forEach(([justice, count], index) => {
                html += `<div class="flex justify-between items-center py-1">
                    <span class="text-sm">${index + 1}. ${justice.split(' ').pop()}</span>
                    <span class="text-sm font-medium">${count}</span>
                </div>`;
            });
            html += '</div>';

            // Most Complex Language
            html += '<div class="bg-white p-4 rounded-lg shadow">';
            html += '<h4 class="font-medium mb-3 text-green-600">Most Complex Language</h4>';
            Object.entries(analysis.most_complex_language).slice(0, 5).forEach(([justice, score], index) => {
                html += `<div class="flex justify-between items-center py-1">
                    <span class="text-sm">${index + 1}. ${justice.split(' ').pop()}</span>
                    <span class="text-sm font-medium">${score}%</span>
                </div>`;
            });
            html += '</div>';

            // Most Formal Language
            html += '<div class="bg-white p-4 rounded-lg shadow">';
            html += '<h4 class="font-medium mb-3 text-purple-600">Most Formal Language</h4>';
            Object.entries(analysis.most_formal_language).slice(0, 5).forEach(([justice, score], index) => {
                html += `<div class="flex justify-between items-center py-1">
                    <span class="text-sm">${index + 1}. ${justice.split(' ').pop()}</span>
                    <span class="text-sm font-medium">${score}</span>
                </div>`;
            });
            html += '</div>';

            // Dissent Specialists
            html += '<div class="bg-white p-4 rounded-lg shadow">';
            html += '<h4 class="font-medium mb-3 text-red-600">Dissent Specialists</h4>';
            Object.entries(analysis.dissent_specialists).slice(0, 5).forEach(([justice, rate], index) => {
                html += `<div class="flex justify-between items-center py-1">
                    <span class="text-sm">${index + 1}. ${justice.split(' ').pop()}</span>
                    <span class="text-sm font-medium">${rate}%</span>
                </div>`;
            });
            html += '</div>';

            html += '</div>';
            container.innerHTML = html;
        }

        function displayTopicTrends(trends) {
            const container = document.getElementById('topic-trends');
            if (!container) return;

            let html = '<h3 class="text-lg font-semibold mb-4">Legal Topic Trends</h3>';
            html += '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

            Object.entries(trends).slice(0, 9).forEach(([topic, data]) => {
                const trendColor = data.trend_direction === 'increasing' ? 'text-green-600' : 
                                 data.trend_direction === 'decreasing' ? 'text-red-600' : 'text-gray-600';
                const trendIcon = data.trend_direction === 'increasing' ? '↗️' : 
                                data.trend_direction === 'decreasing' ? '↘️' : '➡️';

                html += `
                    <div class="bg-white p-4 rounded-lg shadow hover:shadow-md transition-shadow">
                        <h4 class="font-semibold text-sm mb-2">${topic}</h4>
                        <div class="text-xs space-y-1">
                            <div class="flex justify-between">
                                <span>Frequency:</span>
                                <span class="font-medium">${data.total_frequency}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Peak Decade:</span>
                                <span class="font-medium">${data.peak_decade}s</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span>Trend:</span>
                                <span class="${trendColor} font-medium">${trendIcon} ${data.trend_direction}</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        function displayHeatmaps(heatmapData) {
            displayJusticeTopicHeatmap(heatmapData.heatmap_data.justice_topic);
            displayTopicTimeHeatmap(heatmapData.heatmap_data.topic_time);
            displayPrecedentialTimeHeatmap(heatmapData.heatmap_data.precedential_time);
        }

        function displayJusticeTopicHeatmap(data) {
            const container = d3.select('#justice-topic-heatmap');
            if (container.empty()) return;

            container.selectAll('*').remove();

            const margin = {top: 100, right: 100, bottom: 100, left: 150};
            const containerWidth = 800;
            const containerHeight = 600;
            const width = containerWidth - margin.left - margin.right;
            const height = containerHeight - margin.top - margin.bottom;

            const svg = container.append('svg')
                .attr('width', containerWidth)
                .attr('height', containerHeight);

            const g = svg.append('g')
                .attr('transform', `translate(${margin.left},${margin.top})`);

            // Get unique justices and topics
            const justices = [...new Set(data.map(d => d.x))].slice(0, 15); // Limit for readability
            const topics = [...new Set(data.map(d => d.y))];

            // Scales
            const xScale = d3.scaleBand()
                .domain(justices)
                .range([0, width])
                .padding(0.05);

            const yScale = d3.scaleBand()
                .domain(topics)
                .range([0, height])
                .padding(0.05);

            const colorScale = d3.scaleSequential(d3.interpolateBlues)
                .domain([0, d3.max(data, d => d.value)]);

            // Create heatmap cells
            g.selectAll('.cell')
                .data(data.filter(d => justices.includes(d.x)))
                .enter()
                .append('rect')
                .attr('class', 'cell')
                .attr('x', d => xScale(d.x))
                .attr('y', d => yScale(d.y))
                .attr('width', xScale.bandwidth())
                .attr('height', yScale.bandwidth())
                .attr('fill', d => d.value > 0 ? colorScale(d.value) : '#f9fafb')
                .attr('stroke', '#e5e7eb')
                .attr('stroke-width', 0.5)
                .on('mouseover', function(event, d) {
                    d3.select(this).attr('stroke', '#374151').attr('stroke-width', 2);
                    
                    const tooltip = d3.select('body').append('div')
                        .attr('class', 'tooltip')
                        .style('opacity', 0)
                        .style('position', 'absolute')
                        .style('background', 'rgba(0, 0, 0, 0.9)')
                        .style('color', 'white')
                        .style('padding', '12px')
                        .style('border-radius', '4px')
                        .style('font-size', '12px')
                        .style('max-width', '200px');

                    tooltip.transition()
                        .duration(200)
                        .style('opacity', .9);

                    tooltip.html(`
                        <strong>${d.x}</strong> × <strong>${d.y}</strong><br/>
                        Intensity: ${d.value}<br/>
                        Justice Opinions: ${d.data.justice_opinions}<br/>
                        Topic Frequency: ${d.data.topic_frequency}
                    `)
                        .style('left', (event.pageX + 10) + 'px')
                        .style('top', (event.pageY - 28) + 'px');
                })
                .on('mouseout', function() {
                    d3.select(this).attr('stroke', '#e5e7eb').attr('stroke-width', 0.5);
                    d3.selectAll('.tooltip').remove();
                });

            // Add axes
            g.append('g')
                .attr('transform', `translate(0, ${height})`)
                .call(d3.axisBottom(xScale))
                .selectAll('text')
                .style('text-anchor', 'end')
                .attr('dx', '-.8em')
                .attr('dy', '.15em')
                .attr('transform', 'rotate(-45)')
                .style('font-size', '10px');

            g.append('g')
                .call(d3.axisLeft(yScale))
                .selectAll('text')
                .style('font-size', '10px');

            // Add title
            svg.append('text')
                .attr('x', containerWidth / 2)
                .attr('y', 30)
                .attr('text-anchor', 'middle')
                .style('font-size', '14px')
                .style('font-weight', 'bold')
                .text('Justice × Topic Intensity Heatmap');
        }

        function displayTopicTimeHeatmap(data) {
            const container = d3.select('#topic-time-heatmap');
            if (container.empty()) return;

            container.selectAll('*').remove();

            const margin = {top: 80, right: 100, bottom: 100, left: 150};
            const containerWidth = 900;
            const containerHeight = 500;
            const width = containerWidth - margin.left - margin.right;
            const height = containerHeight - margin.top - margin.bottom;

            const svg = container.append('svg')
                .attr('width', containerWidth)
                .attr('height', containerHeight);

            const g = svg.append('g')
                .attr('transform', `translate(${margin.left},${margin.top})`);

            // Get unique topics and time periods
            const topics = [...new Set(data.map(d => d.x))];
            const timePeriods = [...new Set(data.map(d => d.y))].sort();

            // Scales
            const xScale = d3.scaleBand()
                .domain(timePeriods)
                .range([0, width])
                .padding(0.05);

            const yScale = d3.scaleBand()
                .domain(topics)
                .range([0, height])
                .padding(0.05);

            const colorScale = d3.scaleSequential(d3.interpolateReds)
                .domain([0, d3.max(data, d => d.value)]);

            // Create heatmap cells
            g.selectAll('.cell')
                .data(data)
                .enter()
                .append('rect')
                .attr('class', 'cell')
                .attr('x', d => xScale(d.y))
                .attr('y', d => yScale(d.x))
                .attr('width', xScale.bandwidth())
                .attr('height', yScale.bandwidth())
                .attr('fill', d => d.value > 0 ? colorScale(d.value) : '#fef2f2')
                .attr('stroke', '#e5e7eb')
                .attr('stroke-width', 0.5)
                .on('mouseover', function(event, d) {
                    d3.select(this).attr('stroke', '#374151').attr('stroke-width', 2);
                    
                    const tooltip = d3.select('body').append('div')
                        .attr('class', 'tooltip')
                        .style('opacity', 0)
                        .style('position', 'absolute')
                        .style('background', 'rgba(0, 0, 0, 0.9)')
                        .style('color', 'white')
                        .style('padding', '12px')
                        .style('border-radius', '4px')
                        .style('font-size', '12px')
                        .style('max-width', '200px');

                    tooltip.transition()
                        .duration(200)
                        .style('opacity', .9);

                    tooltip.html(`
                        <strong>${d.x}</strong> in <strong>${d.y}</strong><br/>
                        Cases: ${d.value}<br/>
                        Trend: ${d.data.topic_trend}
                    `)
                        .style('left', (event.pageX + 10) + 'px')
                        .style('top', (event.pageY - 28) + 'px');
                })
                .on('mouseout', function() {
                    d3.select(this).attr('stroke', '#e5e7eb').attr('stroke-width', 0.5);
                    d3.selectAll('.tooltip').remove();
                });

            // Add axes
            g.append('g')
                .attr('transform', `translate(0, ${height})`)
                .call(d3.axisBottom(xScale))
                .selectAll('text')
                .style('text-anchor', 'end')
                .attr('dx', '-.8em')
                .attr('dy', '.15em')
                .attr('transform', 'rotate(-45)')
                .style('font-size', '10px');

            g.append('g')
                .call(d3.axisLeft(yScale))
                .selectAll('text')
                .style('font-size', '10px');

            // Add title
            svg.append('text')
                .attr('x', containerWidth / 2)
                .attr('y', 30)
                .attr('text-anchor', 'middle')
                .style('font-size', '14px')
                .style('font-weight', 'bold')
                .text('Legal Topics × Time Period Heatmap');
        }

        function displayPrecedentialTimeHeatmap(data) {
            const container = d3.select('#precedential-time-heatmap');
            if (container.empty()) return;

            container.selectAll('*').remove();

            const margin = {top: 80, right: 100, bottom: 150, left: 200};
            const containerWidth = 1000;
            const containerHeight = 600;
            const width = containerWidth - margin.left - margin.right;
            const height = containerHeight - margin.top - margin.bottom;

            const svg = container.append('svg')
                .attr('width', containerWidth)
                .attr('height', containerHeight);

            const g = svg.append('g')
                .attr('transform', `translate(${margin.left},${margin.top})`);

            // Get unique cases and time periods
            const cases = [...new Set(data.map(d => d.x))].slice(0, 15); // Limit for readability
            const timePeriods = [...new Set(data.map(d => d.y))].sort();

            // Scales
            const xScale = d3.scaleBand()
                .domain(timePeriods)
                .range([0, width])
                .padding(0.05);

            const yScale = d3.scaleBand()
                .domain(cases)
                .range([0, height])
                .padding(0.05);

            const colorScale = d3.scaleSequential(d3.interpolateViridis)
                .domain([0, d3.max(data, d => d.value)]);

            // Create heatmap cells
            g.selectAll('.cell')
                .data(data.filter(d => cases.includes(d.x)))
                .enter()
                .append('rect')
                .attr('class', 'cell')
                .attr('x', d => xScale(d.y))
                .attr('y', d => yScale(d.x))
                .attr('width', xScale.bandwidth())
                .attr('height', yScale.bandwidth())
                .attr('fill', d => d.value > 0 ? colorScale(d.value) : '#f3f4f6')
                .attr('stroke', '#e5e7eb')
                .attr('stroke-width', 0.5)
                .on('mouseover', function(event, d) {
                    d3.select(this).attr('stroke', '#374151').attr('stroke-width', 2);
                    
                    const tooltip = d3.select('body').append('div')
                        .attr('class', 'tooltip')
                        .style('opacity', 0)
                        .style('position', 'absolute')
                        .style('background', 'rgba(0, 0, 0, 0.9)')
                        .style('color', 'white')
                        .style('padding', '12px')
                        .style('border-radius', '4px')
                        .style('font-size', '12px')
                        .style('max-width', '250px');

                    tooltip.transition()
                        .duration(200)
                        .style('opacity', .9);

                    tooltip.html(`
                        <strong>${d.x}</strong><br/>
                        Period: <strong>${d.y}</strong><br/>
                        Precedential Score: ${d.value}<br/>
                        Times Cited: ${d.data.times_cited}<br/>
                        Classification: ${d.data.classification}<br/>
                        Significance: ${d.data.legal_significance}
                    `)
                        .style('left', (event.pageX + 10) + 'px')
                        .style('top', (event.pageY - 28) + 'px');
                })
                .on('mouseout', function() {
                    d3.select(this).attr('stroke', '#e5e7eb').attr('stroke-width', 0.5);
                    d3.selectAll('.tooltip').remove();
                });

            // Add axes
            g.append('g')
                .attr('transform', `translate(0, ${height})`)
                .call(d3.axisBottom(xScale))
                .selectAll('text')
                .style('font-size', '10px');

            g.append('g')
                .call(d3.axisLeft(yScale))
                .selectAll('text')
                .style('font-size', '9px')
                .each(function(d) {
                    // Truncate long case names
                    const text = d3.select(this);
                    if (d.length > 30) {
                        text.text(d.substring(0, 30) + '...');
                    }
                });

            // Add title
            svg.append('text')
                .attr('x', containerWidth / 2)
                .attr('y', 30)
                .attr('text-anchor', 'middle')
                .style('font-size', '14px')
                .style('font-weight', 'bold')
                .text('Major Precedential Cases × Time Period');
        }

        // Precedential analysis initialization is handled in the main DOMContentLoaded handler above
    </script>
</body>
</html>