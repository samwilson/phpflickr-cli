<?php

declare(strict_types=1);

namespace Samwilson\PhpFlickrCli\Command;

use Samwilson\PhpFlickr\PhpFlickr;
use Samwilson\PhpFlickrCli\Template;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class DownloadCommandBase extends CommandBase
{
    /** @return string[] */
    abstract protected function getPhotos(PhpFlickr $flickr, InputInterface $input, int $page) : array;

    protected function configure(): void
    {
        parent::configure();

        $templates = implode(', ', Template::getTemplateNames());
        $this->addOption(
            'template',
            't',
            InputOption::VALUE_OPTIONAL,
            $this->msg('option-template-desc', [$templates]),
            'archive'
        );
        $defaultDest = dirname(__DIR__, 2) . '/photos';
        $this->addOption('dest', 'd', InputOption::VALUE_OPTIONAL, $this->msg('option-dest-desc'), $defaultDest);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $flickr = $this->getFlickr($input);

        // Create the template early, to validate inputs.
        $dest = $input->getOption('dest');
        $templateName = $input->getOption('template');
        $template = new Template($templateName, $dest, $flickr);

        // Get photos.
        $page = 1;
        $allPhotos = [];
        $this->io->block($this->msg('retrieving-photo-metadata'));
        $progressBar1 = new ProgressBar($this->io);
        $progressBar1->start();

        do {
            $photos = $this->getPhotos($flickr, $input, $page);
            $page++;

            if (0 === (int) $photos['total']) {
                $this->io->warning($this->msg('no-photos-found'));

                return 0;
            }

            if (!$progressBar1->getMaxSteps()) {
                $progressBar1->setMaxSteps((int) $photos['total']);
            }

            foreach ($photos['photo'] as $photo) {
                $allPhotos[] = $flickr->photos()->getInfo($photo['id']);
                $progressBar1->advance();
            }
        } while ((int) $photos['page'] !== (int) $photos['pages']);

        $progressBar1->finish();

        $this->io->block($this->msg('compiling-output-files'));

        $progressBar2 = new ProgressBar($this->io, (int) $photos['total']);
        $progressBar2->start();
        $template->setPerPhotoCallback(static function () use ($progressBar2) : void {
            $progressBar2->advance();
        });
        $template->render($allPhotos);
        $progressBar2->finish();

        $this->io->success($this->msg('downloaded-saved-to', [realpath($dest)]));

        return 0;
    }
}
