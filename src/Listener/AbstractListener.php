<?php


declare(strict_types=1);

namespace Ghostwriter\GhostwriterPhpDockerTemplateUpdater\Listener;

use Ghostwriter\GhostwriterPhpDockerTemplateUpdater\PhpSAPI;
use Ghostwriter\GhostwriterPhpDockerTemplateUpdater\PhpVersion;
use Gitonomy\Git\Repository;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

abstract class AbstractListener
{
    /**
     * @var string
     */
    protected const BRANCH_MAIN = 'main';

    public function __construct(
        protected Repository $gitRepository,
        protected SymfonyStyle $symfonyStyle
    ) {
        $cwd = $this->gitRepository->getWorkingDir();
        foreach (PhpVersion::SUPPORTED as $version) {
            if (! is_file(sprintf('%s/%s/Dockerfile', $cwd, $version))) {
                throw new RuntimeException('Invalid dir');
            }
            foreach (PhpSAPI::SUPPORTED as $type) {
                if (! is_file(sprintf('%s/%s/%s/Dockerfile', $cwd, $version, $type))) {
                    throw new RuntimeException('Invalid SAPI dir');
                }
            }
        }
    }

    public function add(string $filePattern): string
    {
        $this->symfonyStyle->info('git:add - ' . $filePattern);
        return $this->gitRepository->run('add', [$filePattern]);
    }

    public function branchName(string $phpVersion, string $tool, string $from, string $to): string
    {
        return strtolower(sprintf('feature/php-%s/bump-%s-from-%s-to-%s', $phpVersion, $tool, $from, $to));
    }

    public function checkout(string $revision, string $branch = null): void
    {
        $this->switch($branch ?? $revision, is_string($branch));
    }

    public function commit(string $message): string
    {
        $this->symfonyStyle->info('git:commit - ' . $message);
        return $this->gitRepository->run('commit', ['--message', $message, '--signoff', '--gpg-sign']);
    }

    public function dockerfilePath(string $phpVersion, string $path = null): string
    {
        if (null === $path) {
            return sprintf('%s/%s/Dockerfile', $this->gitRepository->getWorkingDir(), $phpVersion);
        }

        return sprintf('%s/%s/%s/Dockerfile', $this->gitRepository->getWorkingDir(), $phpVersion, $path);
    }

    public function hasBranch(string $branchName): bool
    {
        return true === $this->gitRepository->getReferences()
            ->hasBranch($branchName);
    }

    public function hasChanges(): bool
    {
        return '' !== trim($this->gitRepository->run('status', ['-s']));
    }

    public function info(string $message): void
    {
        $this->symfonyStyle->info($message);
    }

    public function isBranch(string $branchName): bool
    {
        return trim($this->gitRepository->run('symbolic-ref', ['--short', 'HEAD'])) === $branchName;
    }

    public function pushAndMerge(): void
    {
        $this->gitRepository->run('push');
        Process::fromShellCommandline('gh pr create --base "main" -f')->mustRun();
        Process::fromShellCommandline('gh pr merge --merge')->mustRun();
    }

    public function reset(): void
    {
        $this->symfonyStyle->warning($this->gitRepository->run('reset', ['--hard']));
    }

    public function switch(string $branch, bool $create = false): void
    {
        $this->symfonyStyle->info(sprintf('git:switch - %s', $create ? 'new - ' . $branch : $branch));
        $this->gitRepository->run('switch', $create ? ['-c', $branch] : [$branch]);
    }
}
