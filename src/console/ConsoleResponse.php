<?php

namespace Adige\console;

use Adige\core\BaseResponse;

class ConsoleResponse extends BaseResponse
{
    protected int $exitCode = 0;

    protected string $stdout = '';

    protected string $stderr = '';

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function setExitCode(int $exitCode): static
    {
        $this->exitCode = $exitCode;
        return $this;
    }

    public function getStdout(): string
    {
        return $this->stdout;
    }

    public function setStdout(string $stdout): static
    {
        $this->stdout = $stdout;
        return $this;
    }

    public function appendStdout(string $stdout): static
    {
        $this->stdout .= $stdout;
        return $this;
    }

    public function getStderr(): string
    {
        return $this->stderr;
    }

    public function setStderr(string $stderr): static
    {
        $this->stderr = $stderr;
        return $this;
    }

    public function appendStderr(string $stderr): static
    {
        $this->stderr .= $stderr;
        return $this;
    }

    public function dispatch(): void
    {
        if ($this->stdout !== '') {
            fwrite(STDOUT, $this->stdout);
        }

        if ($this->stderr !== '') {
            fwrite(STDERR, $this->stderr);
        }
    }
}
