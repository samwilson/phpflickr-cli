<?php declare(strict_types = 1);

namespace Samwilson\PhpFlickrCli\Command;

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

    /** @var Intuition */
    private $intuition;

    public function __construct(string $name, Application $application)
    {
        // Add a new parameter to the constructor so that we can set the application before configuring a command.
        // This means we can access the Applicaiton object in self::configure().
        $this->setApplication($application);
        parent::__construct($name);
    }

    /**
     * Add the standard `--config` option that is common to all commands.
     */
    protected function configure(): void
    {
        parent::configure();

        // Set up i18n.
        $this->intuition = new Intuition('phpflickr-cli');
        $this->intuition->registerDomain( 'phpflickr-cli', dirname(__DIR__, 2).'/i18n' );

        $default = dirname(__DIR__, 2) .'/config.yml';
        $this->addOption('config', 'c', InputOption::VALUE_OPTIONAL, $this->msg('option-config-desc'), $default);
    }

    /**
     * Get a localized message.
     *
     * @param string $msg The message to get.
     * @param string[] $vars The message variables.
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
     * @param InputInterface $input
     * @return string[][]
     */
    protected function getConfig(InputInterface $input): array
    {
        $configPath = $input->getOption('config');
        $this->io->block($this->msg('using-config', [$configPath]));

        return Yaml::parseFile($configPath);
    }

    /**
     * @param InputInterface $input
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
        $config = $this->getConfig($input);
        $flickr = new PhpFlickr($config['consumer_key'], $config['consumer_secret']);
        $accessToken = new StdOAuth1Token();
        $accessToken->setAccessToken($config['access_key']);
        $accessToken->setAccessTokenSecret($config['access_secret']);
        $flickr->getOauthTokenStorage()->storeAccessToken('Flickr', $accessToken);
        return $flickr;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title($this->getApplication()->getLongVersion());
        return 1;
    }

}
