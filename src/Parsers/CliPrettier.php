<?php

namespace Chareka\AutoMigrate\Parsers;

use Illuminate\Console\View\Components\Info;
use Illuminate\Console\View\Components\Error;
use Illuminate\Console\View\Components\Warn;

trait CliPrettier
{
    protected function write($component, ...$arguments)
    {
        if ($this->output && class_exists($component)) {
            (new $component($this->output))->render(...$arguments);
        } else {
            foreach ($arguments as $argument) {
                if (is_callable($argument)) {
                    $argument();
                }
            }
        }
    }

    public function writeInfo(...$arguments)
    {
        $this->write(Info::class, ...$arguments);
    }

    public function writeError(...$arguments)
    {
        $this->write(Error::class, ...$arguments);
    }

    public function writeWarning(...$arguments)
    {
        $this->write(Warn::class, ...$arguments);
    }
}