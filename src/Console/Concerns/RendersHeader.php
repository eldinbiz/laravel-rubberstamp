<?php

declare(strict_types=1);

namespace Eldinbiz\RubberStamp\Console\Concerns;

use Illuminate\Console\Command;

/**
 * @mixin Command
 */
trait RendersHeader
{
    /**
     * ASCII logo lines for RUBBER.
     *
     * @var list<string>
     */
    protected array $rubberLogoLines = [
        '  ██████╗  ██╗   ██╗ ██████╗  ██████╗  ███████╗ ██████╗ ',
        '  ██╔══██╗ ██║   ██║ ██╔══██╗ ██╔══██╗ ██╔════╝ ██╔══██╗',
        '  ██████╔╝ ██║   ██║ ██████╔╝ ██████╔╝ █████╗   ██████╔╝',
        '  ██╔══██╗ ██║   ██║ ██╔══██╗ ██╔══██╗ ██╔══╝   ██╔══██╗',
        '  ██║  ██║ ╚██████╔╝ ██████╔╝ ██████╔╝ ███████╗ ██║  ██║',
        '  ╚═╝  ╚═╝  ╚═════╝  ╚═════╝  ╚═════╝  ╚══════╝ ╚═╝  ╚═╝',
    ];

    /**
     * ASCII logo lines for STAMP.
     *
     * @var list<string>
     */
    protected array $stampLogoLines = [
        '           ███████╗ ████████╗  █████╗  ███╗   ███╗ ██████╗ ',
        '           ██╔════╝ ╚══██╔══╝ ██╔══██╗ ████╗ ████║ ██╔══██╗',
        '           ███████╗    ██║    ███████║ ██╔████╔██║ ██████╔╝',
        '           ╚════██║    ██║    ██╔══██║ ██║╚██╔╝██║ ██╔═══╝ ',
        '           ███████║    ██║    ██║  ██║ ██║ ╚═╝ ██║ ██║     ',
        '           ╚══════╝    ╚═╝    ╚═╝  ╚═╝ ╚═╝     ╚═╝ ╚═╝     ',
    ];

    /**
     * ANSI 256 gradient color codes for RUBBER (cyan to blue).
     *
     * @var list<int>
     */
    protected array $rubberGradient = [51, 45, 39, 33, 27, 21];

    /**
     * ANSI 256 gradient color codes for STAMP (teal to emerald).
     *
     * @var list<int>
     */
    protected array $stampGradient = [48, 42, 36, 30, 24, 28];

    /**
     * Render the stylized Boost-style header.
     */
    protected function renderHeader(): void
    {
        $this->newLine();

        $decorated = $this->output->isDecorated();

        foreach ($this->rubberLogoLines as $index => $line) {
            $formatted = $decorated
                ? $this->ansi256Fg($this->rubberGradient[$index] ?? 39, $line)
                : $line;

            $this->output->writeln($formatted);
        }

        foreach ($this->stampLogoLines as $index => $line) {
            $formatted = $decorated
                ? $this->ansi256Fg($this->stampGradient[$index] ?? 36, $line)
                : $line;

            $this->output->writeln($formatted);
        }

        $this->newLine();

        $tagline = ' ✦ RubberStamp :: Automated Test Report Generator ✦ ';
        $badge = $decorated
            ? "\e[48;5;39m\e[30m\e[1m{$tagline}\e[0m"
            : $tagline;

        $this->output->writeln('  '.$badge);
        $this->line('  <fg=gray>Automated test execution, diagnostic logs, and report generation for Pest.</>');
        $this->newLine();
    }

    /**
     * Format text with ANSI 256 foreground color.
     */
    protected function ansi256Fg(int $color, string $text): string
    {
        return "\e[38;5;{$color}m{$text}\e[0m";
    }
}
