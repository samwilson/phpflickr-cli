<?php

declare(strict_types=1);

namespace Samwilson\PhpFlickrCli\Command;

use DirectoryIterator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UploadCommand extends CommandBase
{
    protected function configure() : void
    {
        parent::configure();

        $this->setDescription($this->msg('command-upload-desc'));
        $this->addArgument('source', InputArgument::REQUIRED, $this->msg('argument-source-desc'));
        $this->addOption('hash', null, InputOption::VALUE_OPTIONAL, $this->msg('option-hash-desc'), 'md5');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $sourceArg = $input->getArgument('source');
        $source = realpath($sourceArg);

        if (!$source) {
            $this->io->error($this->msg('upload-source-invalid', [$sourceArg]));

            return 1;
        }

        if (is_dir($source)) {
            $this->uploadDirectory($input, $source);
        } else {
            $this->uploadOne($input, $source);
        }

        return 0;
    }

    protected function uploadDirectory(InputInterface $input, string $dir): void
    {
        foreach (new DirectoryIterator($dir) as $file) {
            if ($file->isDot()) {
                continue;
            }

            if ($file->isDir()) {
                $this->uploadDirectory($input, $file->getPathname());

                continue;
            }

            if ($file->isFile()) {
                $this->uploadOne($input, $file->getPathname());

                continue;
            }
        }
    }

    protected function uploadOne(InputInterface $input, string $filename) : void
    {
        $flickr = $this->getFlickr($input);
        $hashInfo = $this->getHashInfo($input);
        $fileHash = $hashInfo['function']($filename);
        $tag = 'checksum:' . $hashInfo['name'] . '=' . $fileHash;
        $search = $flickr->photos()->search(['user_id' => 'me', 'machine_tags' => $tag]);

        if ((int) $search['total'] >= 1) {
            $shortUrl = $flickr->urls()->getShortUrl($search['photo'][0]['id']);
            $this->io->warning($this->msg('upload-photo-exists', [$shortUrl, $filename]));

            return;
        }

        $result = $flickr->uploader()->upload($filename, null, null, $tag);

        if ('fail' === $result['stat']) {
            $this->io->error($this->msg('upload-failed', [$filename, $result['message']]));

            return;
        }

        $shortUrl = $flickr->urls()->getShortUrl($result['photoid']);
        $this->io->success($this->msg('upload-succeeded', [$filename, $shortUrl]));
    }
}
