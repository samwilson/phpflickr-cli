<?php declare(strict_types = 1);

namespace Samwilson\PhpFlickrCli\Command;

use Samwilson\PhpFlickrCli\Template;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadCommand extends CommandBase
{

    /** @var string */
    protected $tmpDir;

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription($this->msg('command-download-desc'));

        $templates = join(', ', Template::getTemplateNames());
        $this->addOption('template', 't', InputOption::VALUE_OPTIONAL, $this->msg('option-template-desc', [$templates]), 'archive');

        $defaultDest = dirname(__DIR__, 2).'/photos';
        $this->addOption('dest', 'd', InputOption::VALUE_OPTIONAL, $this->msg('option-dest-desc'), $defaultDest);

        $this->addOption('userid', null, InputOption::VALUE_OPTIONAL, $this->msg('option-userid-desc'), 'me');
        $this->addOption('min-upload-date', null, InputOption::VALUE_OPTIONAL, $this->msg('option-min-upload-date-desc'));
        $this->addOption('max-upload-date', null, InputOption::VALUE_OPTIONAL, $this->msg('option-min-date-taken-desc'));
        $this->addOption('min-date-taken', null, InputOption::VALUE_OPTIONAL, $this->msg('option-userid-desc'));
        $this->addOption('max-date-taken', null, InputOption::VALUE_OPTIONAL, $this->msg('option-max-date-taken-desc'));
        $this->addOption('privacy', null, InputOption::VALUE_OPTIONAL, $this->msg('option-privacy-desc'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        // Get photos.
        $flickr = $this->getFlickr($input);
        $page = 1;
        $allPhotos = [];
        $this->io->block('Retrieving photo metadata.');
        $minUploadDate = $input->getOption('min-upload-date')
            ? strtotime($input->getOption('min-upload-date'))
            : null;
        $maxUploadDate = $input->getOption('max-upload-date')
            ? strtotime($input->getOption('max-upload-date'))
            : null;
        $minDateTaken = $input->getOption('min-date-taken')
            ? date('Y-m-d H:i:s', strtotime($input->getOption('min-date-taken')))
            : null;
        $maxDateTaken = $input->getOption('max-date-taken')
            ? date('Y-m-d H:i:s', strtotime($input->getOption('max-date-taken')))
            : null;
        do {
            $photos = $flickr->people()->getPhotos(
                $input->getOption('userid'),
                null,
                $minUploadDate,
                $maxUploadDate,
                $minDateTaken,
                $maxDateTaken,
                null,
                $input->getOption('privacy'),
                null,
                500,
                $page
            );
            if (0 === (int)$photos['total']) {
                $this->io->warning($this->msg('no-photos-found'));
                return 0;
            }

            $progressBar1 = new ProgressBar($this->io, (int)$photos['total']);
            $progressBar1->start();

            foreach ($photos['photo'] as $photo) {
                $allPhotos[] = $flickr->photos()->getInfo($photo['id']);
                $progressBar1->advance();
            }
        } while ($photos['page'] !== $photos['pages']);
        $progressBar1->finish();

        $this->io->block($this->msg('compiling-output-files'));
        $dest = $input->getOption('dest');
        $template = new Template($input->getOption('template'), $dest, $flickr);

        $progressBar2 = new ProgressBar($this->io, (int)$photos['total']);
        $progressBar2->start();
        $template->setPerPhotoCallback(static function () use ($progressBar2): void {
            $progressBar2->advance();
        });
        $template->render($allPhotos);
        $progressBar2->finish();

        $this->io->success($this->msg('downloaded-saved-to', [realpath($dest)]));
        return 0;
    }

}
