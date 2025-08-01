<?php

namespace App\Filament\Resources;

use App\Models\Opinion;
use App\Services\RedisOpinionService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

abstract class RedisOpinionResource extends Resource
{
    protected static ?string $model = null; // We don't use Eloquent models
    
    protected static RedisOpinionService $redisOpinionService;
    
    abstract protected static function getOpinionType(): string;
    
    public static function table(Table $table): Table
    {
        return $table
            ->query(static::getEloquentQuery()) // This will be overridden by modifyQueryUsing
            ->modifyQueryUsing(function () {
                // This won't actually be used since we override the records
                return static::getEloquentQuery();
            })
            ->columns([
                Tables\Columns\TextColumn::make('case_name')
                    ->label('Case Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('justice_name')
                    ->label('Justice')
                    ->searchable()
                    ->sortable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('vote')
                    ->label('Vote')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords($state) : 'N/A')
                    ->badge()
                    ->color(static::getVoteColor()),
                Tables\Columns\TextColumn::make('case_decision_date')
                    ->label('Decision Date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('seniority')
                    ->label('Seniority Rank')
                    ->formatStateUsing(function (?int $state): string {
                        if (!$state) return 'N/A';
                        
                        $suffix = match($state % 10) {
                            1 => $state % 100 === 11 ? 'th' : 'st',
                            2 => $state % 100 === 12 ? 'th' : 'nd', 
                            3 => $state % 100 === 13 ? 'th' : 'rd',
                            default => 'th'
                        };
                        
                        return $state . $suffix . ' most senior';
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
    
    abstract protected static function getVoteColor(): string;
    
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // We don't use Eloquent queries - return a dummy query that will be overridden
        // This is required for Filament compatibility but won't be used
        return \App\Models\Opinion::query()->whereRaw('1 = 0'); // Empty query
    }
    
    public static function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        // Override to return null - we handle pagination manually
        return null;
    }
    
    protected static function getRedisOpinionService(): RedisOpinionService
    {
        return app(RedisOpinionService::class);
    }
    
    public static function getTableRecords(): LengthAwarePaginator
    {
        $service = static::getRedisOpinionService();
        $opinionType = static::getOpinionType();
        
        // Get current page from request
        $page = request('page', 1);
        $perPage = request('tableRecordsPerPage', 25);
        
        // Get paginated data from Redis
        $result = $service->getOpinionsPaginated($opinionType, $page, $perPage);
        
        // Convert to format expected by Filament
        $items = $result['data']->map(function ($opinion) {
            return (object) $opinion;
        });
        
        return new LengthAwarePaginator(
            $items,
            $result['total'],
            $result['per_page'],
            $result['page'],
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }
}