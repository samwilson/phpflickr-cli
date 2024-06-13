<?php

declare(strict_types=1);

namespace Samwilson\PhpFlickrCli\Command;

use Exception;
use Krinkle\Intuition\Intuition;
use OAuth\OAuth1\Token\StdOAuth1Token;
use Samwilson\PhpFlickr\PhpFlickr;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

abstract class CommandBase extends Command
{
    /** @var SymfonyStyle */
    protected $io;

    /** @var PhpFlickr */
    protected $flickr;

    /** @var Intuition */
    private $intuition;

    public function __construct(string $name, Application $application)
    {
        // Add a new parameter to the constructor so that we can set the application before configuring a command.
        // This means we can access the Application object in self::configure().
        $this->setApplication($application);

        parent::__construct($name);
    }

    /**
     * Get the hash function name from the user's input.
     *
     * @param InputInterface $input The input object.
     * @return string[] The names of the hash and its function (keys: 'name' and 'function').
     * @throws Exception On an invalid hash name.
     */
    public function getHashInfo(InputInterface $input): array
    {
        $hash = $input->getOption('hash');

        if (!in_array($hash, ['md5', 'sha1'])) {
            throw new Exception($this->msg('invalid-hash', [$hash]));
        }

        $hashFunction = $hash . '_file';

        if (!function_exists($hashFunction)) {
            throw new Exception($this->msg('hash-function-not-available', [$hashFunction]));
        }

        return ['name' => $hash, 'function' => $hashFunction];
    }

    /**
     * Add the standard things that are common to all commands.
     */
    protected function configure(): void
    {
        parent::configure();

        // Set up i18n.
        $this->intuition = new Intuition('phpflickr-cli');
        $this->intuition->registerDomain('phpflickr-cli', dirname(__DIR__, 2) . '/i18n');

        // Use the current working directory for the config file,
        // but that can on some systems so we fall back to the script's directory.
        $configDir = getcwd();

        if (false === $configDir) {
            $configDir = dirname(__DIR__, 2);
        }

        $default = rtrim($configDir, DIRECTORY_SEPARATOR) . '/config.yml';
        $this->addOption('config', 'c', InputOption::VALUE_OPTIONAL, $this->msg('option-config-desc'), $default);
    }

    /**
     * Get a localized message.
     *
     * @param string        $msg  The message to get.
     * @param string[]|null $vars The message variables.
     * @return string the Localized message.
     */
    protected function msg(string $msg, ?array $vars = []): string
    {
        return $this->intuition->msg($msg, [
            'domain' => 'phpflickr-cli',
            'variables' => $vars,
        ]);
    }

    /**
     * @return string[]
     */
    protected function getConfig(InputInterface $input): array
    {
        $configPath = $input->getOption('config');
        $this->io->block($this->msg('using-config', [$configPath]));

        return Yaml::parseFile($configPath);
    }

    /**
     * @param string[] $config
     */
    protected function setConfig(InputInterface $input, array $config): void
    {
        $configPath = $input->getOption('config');
        file_put_contents($configPath, Yaml::dump($config));
        $this->io->success($this->msg('saved-config', [$configPath]));
    }

    protected function getFlickr(InputInterface $input): PhpFlickr
    {
        if ($this->flickr instanceof PhpFlickr) {
            return $this->flickr;
        }

        $config = $this->getConfig($input);
        $this->flickr = new PhpFlickr($config['consumer_key'], $config['consumer_secret']);
        $accessToken = new StdOAuth1Token();
        $accessToken->setAccessToken($config['access_key']);
        $accessToken->setAccessTokenSecret($config['access_secret']);
        $this->flickr->getOauthTokenStorage()->storeAccessToken('Flickr', $accessToken);

        return $this->flickr;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title($this->getApplication()->getLongVersion());

        return 1;
    }
}
