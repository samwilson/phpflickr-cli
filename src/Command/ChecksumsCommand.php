<?php

namespace Samwilson\PhpFlickrCli\Command;

use Exception;
use Samwilson\PhpFlickr\PhpFlickr;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class ChecksumsCommand extends CommandBase
{

    /** @var string */
    protected $tmpDir;

    protected function configure()
    {
        parent::configure();
        $this->setName('checksums');
        $this->setDescription('Add checksum machine tags to photos already on Flickr.');
        $this->addOption('hash', null, InputOption::VALUE_OPTIONAL, 'The hash function to use.', 'md5');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        // Set up the temporary directory.
        $this->tmpDir = sys_get_temp_dir().'/phpflickr-cli';
        
        $filesystem = new Filesystem();
        if (!$filesystem->exists($this->tmpDir)) {
            $filesystem->mkdir($this->tmpDir, 0755);
            $this->io->success("Created temp directory: $this->tmpDir");
        }

        $flickr = $this->getFlickr($input);

        // Get all photos.
        $page = 1;
        do {
            $params = [
                'user_id' => 'me',
                'page' => $page,
                'per_page' => 500,
                'extras' => 'o_url, tags',
            ];
            $photos = $flickr->people()->getPhotos(
                'me',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                'o_url, tags',
                500,
                $page
            );
            if (0 === $photos['pages']) {
                $this->io->warning('No photos found.');
                return 0;
            }
            $this->io->writeln("Page $page of " . $photos['pages']);
            foreach ($photos['photo'] as $photo) {
                // Process this photo.
                $hashTag = $this->processPhoto($input, $flickr, $photo);
                if (!$hashTag) {
                    exit();
                    continue;
                }
            }
            $page++;
        } while ($photos['page'] !== $photos['pages']);

        // Clean up the temporary directory.
        foreach (preg_grep('|^\..*|', scandir($this->tmpDir, SORT_ASC), PREG_GREP_INVERT) as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
        $this->io->success("Deleted temp directory: $this->tmpDir");

        return 0;
    }

    /**
     * @param $photo
     * @return string|bool The hash machine tag, or false.
     * @throws Exception
     */
    protected function processPhoto(InputInterface $input, PhpFlickr $flickr, $photo)
    {
        // Find the hash function.
        $hash = $input->getOption('hash');
        $hashFunction = $hash . '_file';
        if (!function_exists($hashFunction)) {
            throw new Exception("Hash function not available: $hashFunction");
        }

        // See if the photo has already got a checksum tag.
        preg_match("/(checksum:$hash=.*)/", $photo['tags'], $matches);
        if (isset($matches[1])) {
            // If it's already got a tag, do nothing more.
            $this->io->writeln(sprintf('Already has checksum: %s', $photo['id']));
            return $matches[1];
        }

        $this->io->writeln(sprintf('Adding checksum machine tag to: %s', $photo['id']));

        // Download the file.
        $photoInfo = $flickr->photos()->getInfo($photo['id']);
        $originalUrl = $flickr->buildPhotoURL($photoInfo, 'original');
        $tmpFilename = $this->tmpDir.'/checksumming.'.$photoInfo['originalformat'];
        $downloaded = copy($originalUrl, $tmpFilename);
        if (false === $downloaded) {
            $this->io->error(sprintf('Unable to download: %s', $photo['id']));
            return false;
        }

        // Calculate the file's hash, and remove the temporary file.
        $fileHash = $hashFunction($tmpFilename);
        if (file_exists($tmpFilename)) {
            unlink($tmpFilename);
        }

        // Upload the new tag if it's not already present.
        $hashTag = "checksum:$hash=$fileHash";
        $tagAdded = $flickr->photos()->addTags($photo['id'], [$hashTag]);
        if (isset($tagAdded['err'])) {
            throw new Exception($tagAdded['err']['msg']);
        }
        return $hashTag;
    }
}
