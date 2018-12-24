<?php declare(strict_types = 1);

namespace Samwilson\PhpFlickrCli\Command;

use OAuth\OAuth1\Token\StdOAuth1Token;
use Samwilson\PhpFlickr\PhpFlickr;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Throwable;

final class AuthCommand extends CommandBase
{

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription($this->msg('command-auth-desc', [$this->getApplication()->getName()]));

        $this->addOption('force', 'f', InputOption::VALUE_NONE, $this->msg('option-force-desc'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        // Get the config file, or create one.
        try {
            $config = $this->getConfig($input);
        } catch (Throwable $exception) {
            $configFilePath = $input->getOption('config');
            $filesystem = new Filesystem();
            if ($filesystem->exists($configFilePath)) {
                // If the file does actually exist, it's probably a parse error,
                // but whatever it is we don't want to overwrite it so we bail out here.
                throw $exception;
            }
            $this->io->warning($exception->getMessage());
            $this->io->block($this->msg('config-will-be-created', [$configFilePath]));

            // If we couldn't get the config, ask for the basic config values and then try again.
            $flickrKeyUrl = 'https://www.flickr.com/services/apps/create/apply/';
            $this->io->block($this->msg('go-to-flickr', [$flickrKeyUrl]));
            $customerKey = $this->io->ask($this->msg('consumer-key'));
            $customerSecret = $this->io->ask($this->msg('consumer-secret'));

            // Save the new config values.
            $config = [
                'consumer_key' => $customerKey,
                'consumer_secret' => $customerSecret,
            ];
            $filesystem->touch($configFilePath);
            $filesystem->chmod($configFilePath, 0600);
            $filesystem->dumpFile($configFilePath, Yaml::dump($config));

            // Load again, to make sure it saved correctly.
            $config = $this->getConfig($input);
        }

        $flickr = new PhpFlickr($config['consumer_key'], $config['consumer_secret']);

        // Check connection, just to make sure the consumer key is correct. We don't care about the return value;
        // if there's an issue an exception will be thrown.
        $flickr->test()->testEcho();

        $hasToken = isset($config['access_key']) && isset($config['access_secret']);
        $hasForceOpt = $input->hasOption('force') && $input->getOption('force');
        if (!$hasToken || $hasForceOpt) {
            $this->io->block($this->msg('authorization-required'));
            $url = $flickr->getAuthUrl($this->getPermissionType());
            $this->io->block($this->msg('go-to-auth-url', [$this->getApplication()->getName()]));
            $this->io->writeln($url);
            // Flickr says, at this point:
            // "You have successfully authorized the application XYZ to use your credentials.
            // You should now type this code into the application:"
            $verifier = $this->io->ask($this->msg('past-code-here'), null, static function ($code) {
                return preg_replace('/[^0-9]/', '', $code);
            });
            $accessToken = $flickr->retrieveAccessToken($verifier);
            // Save the access token to config.yml.
            $config['access_key'] = $accessToken->getAccessToken();
            $config['access_secret'] = $accessToken->getAccessTokenSecret();
            $this->setConfig($input, $config);
        } else {
            // This token-construction is usually done in parent::getFlickr(),
            // but we do it again here just to save on reloading the config.
            $accessToken = new StdOAuth1Token();
            $accessToken->setAccessToken($config['access_key']);
            $accessToken->setAccessTokenSecret($config['access_secret']);
            // A storage object has already been created at this point because we called testEcho above.
            $flickr->getOauthTokenStorage()->storeAccessToken('Flickr', $accessToken);
        }

        // Check authorization.
        $userInfo = $flickr->test()->login();
        $this->io->success($this->msg('logged-in-as', [$userInfo['username'], $userInfo['id']]));

        return 1;
    }

    /**
     * Ask the user if they want to authenticate with read, write, or delete permissions.
     *
     * @return string The permission, one of 'read', write', or 'delete'. Defaults to 'read'.
     */
    private function getPermissionType(): string
    {
        $this->io->block($this->msg('permission-explanation', [$this->getApplication()->getName()]));

        $choices = [
            'read' => $this->msg('choice-read'),
            'write' => $this->msg('choice-write'),
            'delete' => $this->msg('choice-delete'),
        ];

        // Note that we're not currently setting a default here, because it is not yet possible
        // to set a non-numeric key as the default. https://github.com/symfony/symfony/issues/15032
        return $this->io->choice($this->msg('select-permission'), $choices);
    }

}
