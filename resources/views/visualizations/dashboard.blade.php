<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Historical Data Visualization</title>

    @vite(['resources/css/app.css'])
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <h1 class="text-3xl font-bold text-gray-900">Historical Data Explorer</h1>
            <p class="mt-2 text-gray-600">200 Years of Data with AI-Powered Insights</p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Data Filters</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Year</label>
                    <input type="number" id="startYear" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" min="1824" max="2024" value="1900">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">End Year</label>
                    <input type="number" id="endYear" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" min="1824" max="2024" value="2024">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select id="category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Categories</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Region</label>
                    <select id="region" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Regions</option>
                    </select>
                </div>
            </div>
            <div class="mt-6 flex gap-4 items-center">
                <button onclick="updateVisualization()" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors">
                    Update Visualization
                </button>
                <select id="chartType" class="px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="line">Line Chart</option>
                    <option value="bar">Bar Chart</option>
                    <option value="scatter">Scatter Plot</option>
                    <option value="heatmap">Heatmap</option>
                    <option value="tree">Tree Map</option>
                </select>
            </div>
        </div>

        <!-- Visualization Area -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Data Visualization</h2>
            <div id="chart" class="w-full" style="height: 500px;"></div>
            <div id="loading" class="hidden flex justify-center items-center h-96">
                <div class="text-gray-500">
                    <svg class="animate-spin h-8 w-8 mx-auto mb-2" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Loading visualization...
                </div>
            </div>
        </div>

        <!-- AI Insights -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">AI Insights</h2>
                <button onclick="generateInsights()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors text-sm">
                    Generate Insights
                </button>
            </div>
            <div id="insights" class="prose max-w-none">
                <p class="text-gray-500">Click "Generate Insights" to get AI-powered analysis of the current data selection.</p>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Ask a specific question:</label>
                <div class="flex gap-2">
                    <input type="text" id="aiQuestion" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., What trends do you see in this data?">
                    <button onclick="askQuestion()" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition-colors">
                        Ask AI
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Global variables
    let currentData = null;
    let currentChart = null;

    // API endpoints
    const API_BASE = '/api/visualizations';

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadFilters();
        updateVisualization();
    });

    // Load available filters
    async function loadFilters() {
        try {
            const response = await fetch(`${API_BASE}/filters`);
            const data = await response.json();

            // Populate category dropdown
            const categorySelect = document.getElementById('category');
            data.categories.forEach(cat => {
                const option = new Option(cat, cat);
                categorySelect.add(option);
            });

            // Populate region dropdown
            const regionSelect = document.getElementById('region');
            data.regions.forEach(region => {
                const option = new Option(region, region);
                regionSelect.add(option);
            });

            // Set year range
            if (data.year_range) {
                document.getElementById('startYear').min = data.year_range.min;
                document.getElementById('startYear').value = Math.max(data.year_range.min, 1900);
                document.getElementById('endYear').max = data.year_range.max;
                document.getElementById('endYear').value = data.year_range.max;
            }
        } catch (error) {
            console.error('Error loading filters:', error);
        }
    }

    // Update visualization
    async function updateVisualization() {
        const chartDiv = document.getElementById('chart');
        const loadingDiv = document.getElementById('loading');

        // Show loading
        chartDiv.style.display = 'none';
        loadingDiv.classList.remove('hidden');

        try {
            const params = new URLSearchParams({
                start_year: document.getElementById('startYear').value,
                end_year: document.getElementById('endYear').value,
                category: document.getElementById('category').value,
                region: document.getElementById('region').value,
                type: document.getElementById('chartType').value,
            });

            const response = await fetch(`${API_BASE}/data?${params}`);
            const result = await response.json();

            currentData = result.data;

            // Clear previous chart
            d3.select('#chart').selectAll('*').remove();

            // Create new chart based on type
            const chartType = document.getElementById('chartType').value;
            switch(chartType) {
                case 'line':
                    createLineChart(currentData);
                    break;
                case 'bar':
                    createBarChart(currentData);
                    break;
                case 'scatter':
                    createScatterPlot(currentData);
                    break;
                case 'heatmap':
                    createHeatmap(currentData);
                    break;
                case 'tree':
                    createTreeMap(currentData);
                    break;
            }

            // Show chart
            chartDiv.style.display = 'block';
            loadingDiv.classList.add('hidden');

        } catch (error) {
            console.error('Error updating visualization:', error);
            chartDiv.innerHTML = '<p class="text-red-500">Error loading visualization</p>';
            chartDiv.style.display = 'block';
            loadingDiv.classList.add('hidden');
        }
    }

    // Create line chart
    function createLineChart(data) {
        const margin = {top: 20, right: 80, bottom: 50, left: 70};
        const width = document.getElementById('chart').offsetWidth - margin.left - margin.right;
        const height = 450 - margin.top - margin.bottom;

        const svg = d3.select('#chart')
            .append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        // Flatten data for scales
        const allData = data.flatMap(d => d.data);

        // Scales
        const xScale = d3.scaleLinear()
            .domain(d3.extent(allData, d => d.x))
            .range([0, width]);

        const yScale = d3.scaleLinear()
            .domain([0, d3.max(allData, d => d.y)])
            .range([height, 0]);

        const colorScale = d3.scaleOrdinal(d3.schemeCategory10);

        // Line generator
        const line = d3.line()
            .x(d => xScale(d.x))
            .y(d => yScale(d.y))
            .curve(d3.curveMonotoneX);

        // Add axes
        svg.append('g')
            .attr('transform', `translate(0,${height})`)
            .call(d3.axisBottom(xScale).tickFormat(d3.format('d')));

        svg.append('g')
            .call(d3.axisLeft(yScale));

        // Add lines
        data.forEach((series, i) => {
            svg.append('path')
                .datum(series.data)
                .attr('fill', 'none')
                .attr('stroke', colorScale(i))
                .attr('stroke-width', 2)
                .attr('d', line);

            // Add dots
            svg.selectAll(`.dot-${i}`)
                .data(series.data)
                .enter().append('circle')
                .attr('cx', d => xScale(d.x))
                .attr('cy', d => yScale(d.y))
                .attr('r', 3)
                .attr('fill', colorScale(i));
        });

        // Add legend
        const legend = svg.selectAll('.legend')
            .data(data)
            .enter().append('g')
            .attr('class', 'legend')
            .attr('transform', (d, i) => `translate(${width + 10},${i * 20})`);

        legend.append('rect')
            .attr('width', 10)
            .attr('height', 10)
            .attr('fill', (d, i) => colorScale(i));

        legend.append('text')
            .attr('x', 15)
            .attr('y', 9)
            .style('font-size', '12px')
            .text(d => d.id);
    }

    // Create bar chart
    function createBarChart(data) {
        const margin = {top: 20, right: 80, bottom: 50, left: 70};
        const width = document.getElementById('chart').offsetWidth - margin.left - margin.right;
        const height = 450 - margin.top - margin.bottom;

        const svg = d3.select('#chart')
            .append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        // Get categories from first data point
        const categories = Object.keys(data[0]).filter(k => k !== 'year');

        // Scales
        const x0Scale = d3.scaleBand()
            .domain(data.map(d => d.year))
            .rangeRound([0, width])
            .paddingInner(0.1);

        const x1Scale = d3.scaleBand()
            .domain(categories)
            .rangeRound([0, x0Scale.bandwidth()])
            .padding(0.05);

        const yScale = d3.scaleLinear()
            .domain([0, d3.max(data, d => d3.max(categories, cat => d[cat]))])
            .nice()
            .rangeRound([height, 0]);

        const colorScale = d3.scaleOrdinal(d3.schemeCategory10);

        // Add axes
        svg.append('g')
            .attr('transform', `translate(0,${height})`)
            .call(d3.axisBottom(x0Scale));

        svg.append('g')
            .call(d3.axisLeft(yScale));

        // Add bars
        const yearGroups = svg.selectAll('.year-group')
            .data(data)
            .enter().append('g')
            .attr('transform', d => `translate(${x0Scale(d.year)},0)`);

        yearGroups.selectAll('rect')
            .data(d => categories.map(cat => ({key: cat, value: d[cat]})))
            .enter().append('rect')
            .attr('x', d => x1Scale(d.key))
            .attr('y', d => yScale(d.value))
            .attr('width', x1Scale.bandwidth())
            .attr('height', d => height - yScale(d.value))
            .attr('fill', d => colorScale(d.key));
    }

    // Create scatter plot
    function createScatterPlot(data) {
        const margin = {top: 20, right: 80, bottom: 50, left: 70};
        const width = document.getElementById('chart').offsetWidth - margin.left - margin.right;
        const height = 450 - margin.top - margin.bottom;

        const svg = d3.select('#chart')
            .append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        const allData = data.flatMap(d => d.data);

        // Scales
        const xScale = d3.scaleLinear()
            .domain(d3.extent(allData, d => d.x))
            .range([0, width]);

        const yScale = d3.scaleLinear()
            .domain([0, d3.max(allData, d => d.y)])
            .range([height, 0]);

        const colorScale = d3.scaleOrdinal(d3.schemeCategory10);

        // Add axes
        svg.append('g')
            .attr('transform', `translate(0,${height})`)
            .call(d3.axisBottom(xScale).tickFormat(d3.format('d')));

        svg.append('g')
            .call(d3.axisLeft(yScale));

        // Add dots
        data.forEach((series, i) => {
            svg.selectAll(`.dot-${i}`)
                .data(series.data)
                .enter().append('circle')
                .attr('cx', d => xScale(d.x))
                .attr('cy', d => yScale(d.y))
                .attr('r', 5)
                .attr('fill', colorScale(i))
                .attr('opacity', 0.7)
                .on('mouseover', function(event, d) {
                    d3.select(this).attr('r', 8);
                    // Add tooltip
                })
                .on('mouseout', function(event, d) {
                    d3.select(this).attr('r', 5);
                });
        });
    }

    // Create heatmap
    function createHeatmap(data) {
        const margin = {top: 80, right: 25, bottom: 30, left: 100};
        const width = document.getElementById('chart').offsetWidth - margin.left - margin.right;
        const height = 450 - margin.top - margin.bottom;

        const svg = d3.select('#chart')
            .append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        // Get unique values
        const years = [...new Set(data.map(d => d.x))].sort();
        const categories = [...new Set(data.map(d => d.y))];

        // Scales
        const xScale = d3.scaleBand()
            .range([0, width])
            .domain(years)
            .padding(0.05);

        const yScale = d3.scaleBand()
            .range([height, 0])
            .domain(categories)
            .padding(0.05);

        const colorScale = d3.scaleSequential(d3.interpolateYlOrRd)
            .domain([0, d3.max(data, d => d.value)]);

        // Add axes
        svg.append('g')
            .attr('transform', `translate(0,${height})`)
            .call(d3.axisBottom(xScale));

        svg.append('g')
            .call(d3.axisLeft(yScale));

        // Add cells
        svg.selectAll()
            .data(data)
            .enter()
            .append('rect')
            .attr('x', d => xScale(d.x))
            .attr('y', d => yScale(d.y))
            .attr('width', xScale.bandwidth())
            .attr('height', yScale.bandwidth())
            .style('fill', d => colorScale(d.value));
    }

    // Create tree map
    function createTreeMap(data) {
        const width = document.getElementById('chart').offsetWidth;
        const height = 450;

        const svg = d3.select('#chart')
            .append('svg')
            .attr('width', width)
            .attr('height', height);

        const root = d3.hierarchy(data)
            .sum(d => d.value)
            .sort((a, b) => b.value - a.value);

        d3.treemap()
            .size([width, height])
            .padding(2)
            (root);

        const colorScale = d3.scaleOrdinal(d3.schemeCategory10);

        const leaf = svg.selectAll('g')
            .data(root.leaves())
            .enter().append('g')
            .attr('transform', d => `translate(${d.x0},${d.y0})`);

        leaf.append('rect')
            .attr('id', d => d.id)
            .attr('width', d => d.x1 - d.x0)
            .attr('height', d => d.y1 - d.y0)
            .attr('fill', d => colorScale(d.parent.data.name))
            .attr('stroke', 'white');

        leaf.append('text')
            .attr('x', 4)
            .attr('y', 20)
            .text(d => d.data.name)
            .attr('font-size', '12px')
            .attr('fill', 'white');
    }

    // Generate AI insights
    async function generateInsights() {
        const insightsDiv = document.getElementById('insights');
        insightsDiv.innerHTML = '<p class="text-gray-500">Generating insights...</p>';

        try {
            const params = new URLSearchParams({
                start_year: document.getElementById('startYear').value,
                end_year: document.getElementById('endYear').value,
                category: document.getElementById('category').value,
                region: document.getElementById('region').value,
            });

            const response = await fetch(`${API_BASE}/insights?${params}`);
            const result = await response.json();

            if (result.analysis) {
                insightsDiv.innerHTML = `<div class="whitespace-pre-wrap">${result.analysis}</div>`;
            } else {
                insightsDiv.innerHTML = '<p class="text-red-500">Unable to generate insights</p>';
            }
        } catch (error) {
            console.error('Error generating insights:', error);
            insightsDiv.innerHTML = '<p class="text-red-500">Error generating insights</p>';
        }
    }

    // Ask specific question
    async function askQuestion() {
        const question = document.getElementById('aiQuestion').value;
        if (!question) return;

        const insightsDiv = document.getElementById('insights');
        insightsDiv.innerHTML = '<p class="text-gray-500">Processing your question...</p>';

        try {
            const params = new URLSearchParams({
                start_year: document.getElementById('startYear').value,
                end_year: document.getElementById('endYear').value,
                category: document.getElementById('category').value,
                region: document.getElementById('region').value,
                question: question,
            });

            const response = await fetch(`${API_BASE}/insights?${params}`);
            const result = await response.json();

            if (result.analysis) {
                insightsDiv.innerHTML = `
                        <div class="mb-4">
                            <h4 class="font-semibold">Your Question:</h4>
                            <p class="text-gray-600">${question}</p>
                        </div>
                        <div class="whitespace-pre-wrap">${result.analysis}</div>
                    `;
            } else {
                insightsDiv.innerHTML = '<p class="text-red-500">Unable to process question</p>';
            }
        } catch (error) {
            console.error('Error processing question:', error);
            insightsDiv.innerHTML = '<p class="text-red-500">Error processing question</p>';
        }
    }
</script>
</body>
</html>
