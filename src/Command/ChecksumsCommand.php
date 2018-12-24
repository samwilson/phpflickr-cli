<?php declare(strict_types = 1);

namespace Samwilson\PhpFlickrCli\Command;

use Exception;
use Samwilson\PhpFlickr\PhpFlickr;
use Samwilson\PhpFlickr\Util;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class ChecksumsCommand extends CommandBase
{

    /** @var string */
    protected $tmpDir;

    protected function configure(): void
    {
        parent::configure();
        $this->setName('checksums');
        $this->setDescription('Add checksum machine tags to photos already on Flickr.');
        $this->addOption('hash', null, InputOption::VALUE_OPTIONAL, 'The hash function to use. Either "md5" or "sha1".', 'md5');
    }

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
                    return 1;
                }
            }
            $page++;
        } while ($photos['page'] !== $photos['pages']);

        // Clean up the temporary directory.
        $tmpFiles = scandir($this->tmpDir, SORT_ASC);
        foreach (preg_grep('|^\..*|', $tmpFiles, PREG_GREP_INVERT) as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
        $this->io->success("Deleted temp directory: $this->tmpDir");

        return 0;
    }

    /**
     * @param InputInterface $input
     * @param PhpFlickr $flickr
     * @param string[] $photo
     * @return string|bool The hash machine tag, or false.
     * @throws \Exception
     */
    protected function processPhoto(InputInterface $input, PhpFlickr $flickr, array $photo)
    {
        // Find the hash function.
        $hashInfo = $this->getHashInfo($input);

        // See if the photo has already got a checksum tag.
        preg_match("/(checksum:{$hashInfo['name']}=.*)/", $photo['tags'], $matches);
        if (isset($matches[1])) {
            // If it's already got a tag, do nothing more.
            $this->io->writeln(sprintf('Already has checksum: %s', $photo['id']));
            return $matches[1];
        }

        $shortUrl = 'https://flic.kr/p/'.Util::base58encode($photo['id']);
        $this->io->writeln(sprintf('Adding checksum machine tag to: %s %s', $photo['id'], $shortUrl));

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
        $fileHash = $hashInfo['function']($tmpFilename);
        if (file_exists($tmpFilename)) {
            unlink($tmpFilename);
        }

        // Upload the new tag if it's not already present.
        $hashTag = "checksum:{$hashInfo['name']}=$fileHash";
        $tagAdded = $flickr->photos()->addTags($photo['id'], [$hashTag]);
        if (isset($tagAdded['err'])) {
            throw new Exception($tagAdded['err']['msg']);
        }
        return $hashTag;
    }

    /**
     * Get the hash function name from the user's input.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The input object.
     * @return string[] The names of the hash and its function (keys: 'name' and 'function').
     * @throws \Exception On an invalid hash name.
     */
    public function getHashInfo(InputInterface $input): array
    {
        $hash = $input->getOption('hash');

        if (!in_array($hash, ['md5', 'sha1'])) {
            throw new Exception("Hash function must be either 'md5' or 'sha1'. You said: $hash");
        }

        $hashFunction = $hash . '_file';

        if (!function_exists($hashFunction)) {
            throw new Exception("Hash function not available: $hashFunction");
        }

        return ['name' => $hash, 'function' => $hashFunction];
    }

}
