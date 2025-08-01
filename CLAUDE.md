# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a historical data visualization application built with Laravel 12 (PHP 8.2+) backend, Filament admin panel, and Vite + TailwindCSS 4 frontend. The project analyzes and visualizes 200+ years of historical data using AI-powered insights through OpenAI's API.

## Key Development Commands

### Development Environment
- `composer dev` - Start full development stack (Laravel server, queue worker, logs, and Vite dev server)
- `php artisan serve` - Start Laravel development server only
- `npm run dev` - Start Vite development server for frontend assets
- `npm run build` - Build production frontend assets

### Database & Testing
- `php artisan migrate` - Run database migrations
- `php artisan test` or `composer test` - Run PHPUnit tests
- `php artisan queue:listen` - Start queue worker for background jobs

### Filament Admin Panel
- `php artisan make:filament-user` - Create admin user for Filament panel
- `php artisan make:filament-resource ModelName` - Create new Filament resource
- `php artisan make:filament-widget WidgetName` - Create dashboard widget
- Admin panel accessible at `/admin` route

### Docker Development
- `docker-compose up` or `./vendor/bin/sail up` - Start Laravel Sail development environment
- Uses MySQL 8.0 database container

## Architecture Overview

### Backend (Laravel)
- **Models**: 
  - `HistoricalRecord` - handles 200+ years of historical data with year ranges, categories, and regions
  - `LlmAnalysis` - stores AI analysis results with caching
- **Controllers**: `VisualizationController` for web views, `Api\VisualizationController` for API endpoints
- **Services**: `LlmAnalysisService` integrates with OpenAI API for AI-powered data analysis with caching
- **Console**: `ImportHistoricalData` command for data import processes

### Filament Admin Panel
- **Resources**:
  - `HistoricalRecordResource` - manage historical data with advanced filtering by year range, category, region
  - `LlmAnalysisResource` - view AI analysis queries, responses, and token usage
- **Features**: Full CRUD operations, advanced search/filtering, bulk actions, data visualization
- **Access**: Available at `/admin` route

### Data Structure
- Historical data stored in `/json/` directory with thousands of JSON files
- File naming pattern: `{year_range}.{identifier}.json` (e.g., `1789_1850.10us87.json`)
- Each file contains Supreme Court decisions and other historical records with detailed metadata

### Frontend
- Vite build system with Laravel plugin
- TailwindCSS 4 for styling
- Entry points: `resources/css/app.css` and `resources/js/app.js`

### Key Features
- Time-series data visualization with filtering by year range, category, and region
- AI-powered analysis using OpenAI API with multi-level caching (Redis + database)
- Historical data import and processing capabilities
- API endpoints for data visualization components

## Development Workflow

1. **Setup**: Run `composer install && npm install`
2. **Environment**: Copy `.env.example` to `.env` and configure database/API keys
3. **Database**: Run `php artisan migrate` 
4. **Development**: Use `composer dev` for full development stack
5. **Testing**: Run `composer test` before committing changes

## Important Notes

- OpenAI API key required in `.env` for LLM analysis features
- Historical data files are pre-processed JSON containing Supreme Court and other historical records
- The `LlmAnalysisService` implements intelligent caching to minimize API costs
- Database uses SQLite for development (configurable in `.env`)
- Filament admin panel provides comprehensive data management interface
- Admin user creation required: `php artisan make:filament-user` (interactive command)