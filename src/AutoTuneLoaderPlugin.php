<?php
declare(strict_types=1);

namespace jæm3l\AutoTuneLoader;

use Codesound\Mapper;
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
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function dumpAutoTuneLoader(Event $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');

        $values = [];
        if ($handle = opendir($vendorDir)) {
            while (false !== ($entry = readdir($handle))) {
                $file = $vendorDir.DIRECTORY_SEPARATOR.$entry;
                if (is_dir($file)) {
                    continue;
                }

                $index = $this->bignum($file);
                $length = filesize($file);
                $values[] = [$index, $length];
            }
            closedir($handle);
        }

        $tuples = (new Mapper())->map($values);
        $sequence = Sequence::fromTuples($tuples);
        $player = new Player($sequence);

        (new SoundDumper())->dump($player, __DIR__.'/autotuneloader.ul', __DIR__.'/autotuneloader.wav');
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
