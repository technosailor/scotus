# 🏛️ Supreme Court Data Visualization - GitHub Deployment

A comprehensive Laravel 12 application for Supreme Court case analysis and visualization with AI-powered insights.

## 🚀 Quick Start Options

### Option 1: GitHub Codespaces (Recommended)
**Instant full-stack development environment with zero setup:**

1. Click **"Code"** → **"Codespaces"** → **"Create codespace on main"**
2. Wait 5-10 minutes for automatic setup
3. Access your app at the forwarded port 8000
4. Start coding immediately!

**What you get:**
- ✅ PHP 8.4 + Laravel 12 fully configured
- ✅ Node.js 18 + Vite development server
- ✅ SQLite database with migrations
- ✅ Redis caching
- ✅ Ollama LLM with llama2:7b-chat model
- ✅ Nginx production server
- ✅ All extensions and dependencies installed
- ✅ VS Code with Laravel extensions

### Option 2: GitHub Pages Demo
**Live demo automatically deployed:**

Visit: `https://YOUR_USERNAME.github.io/historical-data-viz`

### Option 3: Download & Run Locally
**Get the latest release:**

1. Go to **Releases** → Download `supreme-court-app-*.tar.gz`
2. Extract: `tar -xzf supreme-court-app-*.tar.gz`  
3. Run: `./install.sh`
4. Start: `php artisan serve`

## 🛠️ What This Application Does

### Core Features
- **📊 Interactive Visualizations**: D3.js charts for case analysis and trends
- **⚖️ Precedential Analysis**: Track case importance by citation frequency  
- **👨‍⚖️ Justice Language Patterns**: Analyze writing styles and complexity
- **📈 Topic Trend Analysis**: Identify legal topic patterns across decades
- **🔥 Interactive Heatmaps**: Visualize relationships between justices, topics, and time
- **🤖 AI-Powered Insights**: Local LLM analysis with Ollama
- **🎯 Advanced Search**: Intelligent case search and filtering
- **💬 Chat Interface**: Natural language queries about cases
- **⚙️ Admin Panel**: Filament-powered data management

### Data Sources
- **200+ years** of Supreme Court cases
- **Thousands of opinions** from majority, concurrence, and dissent
- **Justice biographical data** and appointment history  
- **Legal topic categorization** and trend analysis
- **Citation networks** and precedential importance scoring

## 🔧 Technical Stack

### Backend
- **PHP 8.4** - Latest PHP with performance improvements
- **Laravel 12** - Modern PHP framework with advanced features
- **SQLite/MySQL** - Flexible database options
- **Redis** - High-performance caching and session storage
- **Filament** - Modern admin panel for data management

### Frontend  
- **Vite** - Lightning-fast build tool and dev server
- **TailwindCSS 4** - Utility-first CSS framework
- **D3.js** - Advanced data visualizations
- **Alpine.js** - Lightweight reactive framework

### AI & Analysis
- **Ollama** - Local LLM server with llama2:7b-chat
- **OpenAI API** - Optional cloud AI integration
- **Custom Analysis Services** - Precedential ranking, language analysis, topic extraction

### DevOps & Deployment
- **Docker** - Containerized deployment with PHP 8.4, Nginx, Redis, Ollama
- **GitHub Actions** - Automated testing, building, and deployment
- **GitHub Codespaces** - Instant development environment
- **GitHub Pages** - Automated static demo deployment

## 📚 API Endpoints

### Main Application
- `GET /` - Interactive dashboard with all visualizations
- `GET /admin` - Filament admin panel for data management

### Supreme Court Data API
- `GET /api/supreme-court/search` - Search cases with filters
- `GET /api/supreme-court/cases-per-term` - Case counts by term
- `GET /api/supreme-court/justice-opinion-stats` - Justice statistics
- `GET /api/supreme-court/timeline` - Historical timeline data
- `GET /api/supreme-court/justice-network` - Justice relationship networks
- `POST /api/supreme-court/chat` - Natural language case queries

### Precedential Analysis API  
- `GET /api/supreme-court/precedential-analysis` - Major precedential cases
- `GET /api/supreme-court/justice-language-patterns` - Justice writing analysis
- `GET /api/supreme-court/topic-trends` - Legal topic trends over time
- `GET /api/supreme-court/heatmap-data` - Interactive heatmap data

### Redis Opinion Data API
- `GET /api/redis/opinions` - Opinion data from Redis
- `GET /api/redis/opinions/search` - Search opinions
- `GET /api/redis/opinions/statistics` - Opinion statistics
- `GET /api/redis/opinions/case/{caseId}` - Opinions by case
- `GET /api/redis/opinions/justice/{justiceId}` - Opinions by justice

## 🐳 Docker Deployment

### Quick Docker Run
```bash
# Build the image
docker build -t supreme-court-viz .

# Run with all services
docker run -p 80:80 -p 8000:8000 -p 11434:11434 supreme-court-viz
```

### Docker Compose (Advanced)
```yaml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "80:80"      # Nginx
      - "8000:8000"  # Laravel
      - "11434:11434" # Ollama
    volumes:
      - ./storage:/var/www/html/storage
      - ./database:/var/www/html/database
    environment:
      - APP_ENV=production
      - DB_CONNECTION=sqlite
```

## 🔨 Development Commands

### Laravel Artisan Commands
```bash
# Custom Application Commands
php artisan supreme-court:import-data    # Import JSON case data
php artisan supreme-court:normalize      # Clean and normalize data
php artisan redis:migrate-opinions       # Migrate opinions to Redis
php artisan llm:analyze-opinions         # Run AI analysis on opinions
php artisan precedential:analyze         # Analyze case precedential importance

# Standard Laravel Commands  
php artisan serve                        # Start development server
php artisan migrate                      # Run database migrations
php artisan queue:work                   # Start background job processing
php artisan make:filament-user           # Create admin user
```

### Development Stack
```bash
# Full development stack (recommended)
composer dev                             # Laravel + Vite + Queue + Logs

# Individual services
php artisan serve                        # Laravel server only  
npm run dev                              # Vite dev server only
php artisan queue:listen                 # Queue worker only
php artisan pail                         # Application logs
```

## 📋 Requirements

### System Requirements
- **PHP 8.4+** with extensions: `mbstring`, `xml`, `ctype`, `iconv`, `intl`, `pdo`, `sqlite3`, `redis`, `gd`, `zip`, `bcmath`
- **Composer 2.0+** for PHP dependency management
- **Node.js 18+** and npm for frontend asset building
- **SQLite** or **MySQL 8.0+** for database
- **Redis** (optional) for caching and sessions
- **Git** for version control

### Development Requirements (Optional)
- **Docker** for containerized deployment
- **Ollama** for local LLM functionality
- **OpenAI API key** for cloud AI features

## 🎯 Getting Started

### 1. GitHub Codespaces (Zero Setup)
Just click **"Code" → "Codespaces" → "Create codespace"** and you're ready to go!

### 2. Local Development  
```bash
# Clone the repository
git clone https://github.com/YOUR_USERNAME/historical-data-viz.git
cd historical-data-viz

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Setup database
touch database/database.sqlite
php artisan migrate

# Build assets and start
npm run build
php artisan serve
```

### 3. Docker Deployment
```bash
# Simple Docker run
docker build -t supreme-court-viz .
docker run -p 80:80 supreme-court-viz

# Access at http://localhost
```

## 🌟 Key Features in Detail

### Precedential Analysis System
- **Citation Network Analysis**: Maps how cases reference each other
- **Precedential Scoring**: Calculates importance based on citation frequency
- **Major Case Identification**: Highlights landmark decisions
- **Temporal Analysis**: Tracks precedential value over time

### Justice Language Analysis
- **Writing Pattern Recognition**: Analyzes complexity, formality, and style
- **Opinion Type Classification**: Majority, concurrence, dissent patterns
- **Comparative Analysis**: Justice-to-justice writing comparisons
- **Evolution Tracking**: How Justice writing changes over time

### Topic Trend Analysis  
- **Legal Topic Extraction**: Identifies major constitutional and legal themes
- **Temporal Trending**: Tracks topic frequency across decades
- **Peak Period Identification**: Finds when topics were most prominent
- **Trend Direction Analysis**: Rising, declining, or stable topic patterns

### Interactive Visualizations
- **Justice × Topic Heatmap**: Shows Justice expertise areas
- **Topic × Time Heatmap**: Visualizes legal trend evolution  
- **Precedential × Time Heatmap**: Major case temporal distribution
- **Network Graphs**: Justice agreement patterns and case citations
- **Timeline Charts**: Historical case frequency and sentiment

## 🤝 Contributing

This application is designed for:
- **Legal Researchers** studying Supreme Court patterns
- **Data Scientists** analyzing judicial data
- **Developers** building legal technology tools  
- **Students** learning about constitutional law
- **Journalists** covering Supreme Court decisions

## 📄 License

Built with ❤️ using Laravel, D3.js, TailwindCSS, and modern web technologies.

---

## 🚀 Deploy Now!

Choose your deployment method:

[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?repo=YOUR_REPO_NAME)

[![Deploy to GitHub Pages](https://img.shields.io/badge/Deploy%20to-GitHub%20Pages-blue)](../../actions)

[![Download Latest Release](https://img.shields.io/badge/Download-Latest%20Release-green)](../../releases/latest)

**Happy analyzing! 🏛️⚖️📊**