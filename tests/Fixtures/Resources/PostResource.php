<?php

declare(strict_types=1);

namespace Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Models\Post;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\Pages\CreatePost;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\Pages\EditPost;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\Pages\ListPosts;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required(),
            Textarea::make('body'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
