<?php
declare(strict_types=1);

namespace jæm3l\AutoTuneLoader;

use Codesound\Converter;
use Codesound\Player;
use Codesound\Sequence;
use Codesound\SoundDumper;
use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class AutoTuneLoaderPlugin implements PluginInterface, EventSubscriberInterface
{
    private static $activated = true;

    public function activate(Composer $composer, IOInterface $io): void
    {
        if (!$this->isSoxInstalled()) {
            self::$activated = false;
            $io->writeError('<error>Please install sox to use auto-tune-loader.</error>');
        }
    }

    public function dumpAutoTuneLoader(Event $event): void
    {
        $composer = $event->getComposer();
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $sourceDirs = $this->getSourceDirs($composer->getPackage()->getAutoload());
        $baseDir = dirname($vendorDir);
        array_push($sourceDirs, $vendorDir);

        $event->getIO()->write('<info>Generating your project-tune</info>');

        $values = [];
        foreach ($this->getSourceIterator($sourceDirs) as $sourceFile) {
            if (!is_readable($sourceFile)) {
                continue;
            }
            $index = $this->bignum($sourceFile);
            $length = filesize($sourceFile);
            $values[] = [$index, $length];
        }

        $tuples = (new Converter())->convert($values);
        $sequence = Sequence::fromTuples($tuples);
        $player = new Player($sequence);

        (new SoundDumper())->dump($player, $baseDir.'/autotuneloader.ul', $baseDir.'/autotuneloader.wav');
        @unlink($baseDir.'/autotuneloader.ul');
    }

    public static function getSubscribedEvents(): array
    {
        if (!self::$activated) {
            return [];
        }

        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'dumpAutoTuneLoader',
        ];
    }

    private function isSoxInstalled(): bool
    {
        exec('which sox', $output, $code);

        return 0 === $code;
    }

    private function getSourceDirs(array $autoload): array
    {
        $sourceDirs = [];
        foreach ($autoload as $type => $mappings) {
            if (in_array($type, ['files', 'classmap'], true)) {
                $sourceDirs = array_merge($sourceDirs, array_map(function ($file) {
                    return realpath(dirname($file));
                }, $mappings));
            } else {
                $sourceDirs = array_merge($sourceDirs, array_map('realpath', array_values($mappings)));
            }
        }

        return array_unique($sourceDirs);
    }

    private function getSourceIterator(array $sourceDirs): \Iterator
    {
        $sourceIterator = new \AppendIterator();
        foreach ($sourceDirs as $sourceDir) {
            $iterator = new \RecursiveDirectoryIterator(
                $sourceDir,
                \RecursiveDirectoryIterator::CURRENT_AS_PATHNAME
                | \RecursiveDirectoryIterator::SKIP_DOTS
                | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS
            );

            $sourceIterator->append(
                $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST)
            );
        }

        return new \RegexIterator($sourceIterator , '/^.+\.php/i');
    }

    private function bignum(string $filepath): int
    {
        return (int) hexdec(substr(sha1($filepath), 0, 15));
    }
}
