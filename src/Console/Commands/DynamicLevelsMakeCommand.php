<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Console\Commands;

use Illuminate\Console\Command;

final class DynamicLevelsMakeCommand extends Command
{
    protected $signature = 'dynamic:quote';
    protected $description = 'Display a random inspirational quote from GMCKS';

    private array $quotes = [
        [
            "quote" => "Do not blame other people for your problems. Be responsible for your actions.",
            "author" => "GMCKS",
            "source" => "Pranic Healing",
            "book" => "GLS - Experience Being",
            "page" => 19
        ],
        [
            "quote" => "Life is governed by cycles, by change. Sometimes you are up, sometimes you are down.",
            "author" => "GMCKS",
            "source" => "Pranic Healing",
            "book" => "The Existence of God is Self-Evident",
            "page" => 36
        ],
        [
            "quote" => "The soul develops by learning, by evolving. This is the purpose of life.",
            "author" => "GMCKS",
            "source" => "Pranic Healing",
            "book" => "Achieving Oneness with the Higher Soul",
            "page" => 5
        ]
        // Add more quotes as needed
    ];

    public function handle(): void
    {
        // Pick a random quote from the array
        $quoteData = $this->quotes[array_rand($this->quotes)];

        // Format the quote display
        $quoteDisplay = "\"{$quoteData['quote']}\"\n\n-- {$quoteData['author']}, {$quoteData['source']}.\nBook - \"{$quoteData['book']}\" pg #{$quoteData['page']}";

        // Display the quote to the console
        $this->info($quoteDisplay);
    }
}
