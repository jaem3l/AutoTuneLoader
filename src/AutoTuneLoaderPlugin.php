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
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class AutoTuneLoaderPlugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function dumpAutoTuneLoader(Event $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $baseDir = dirname($vendorDir);

        $event->getIO()->write('<info>Generating your auto-tune</info>');

        $finder = (new Finder())
            ->name('*.php')
            ->in($vendorDir);

        $values = [];
        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $path = $file->getRealPath();

            if (false === $path) {
                continue;
            }

            $index = $this->bignum($path);
            $length = filesize($path);
            $values[] = [$index, $length];
        }

        $tuples = (new Converter())->convert($values);
        $sequence = Sequence::fromTuples($tuples);
        $player = new Player($sequence);

        (new SoundDumper())->dump($player, $baseDir.'/autotuneloader.ul', $baseDir.'/autotuneloader.wav');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'dumpAutoTuneLoader',
        ];
    }

    private function bignum(string $filepath): int
    {
        return (int) hexdec(substr(sha1($filepath), 0, 15));
    }
}
