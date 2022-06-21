<?php
declare(strict_types=1);
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagImportExport\Components\Service;

use Shopware\Components\Model\ModelManager;
use Shopware\Components\Model\ModelRepository;
use SwagImportExport\Components\Factories\ProfileFactory;
use SwagImportExport\Components\UploadPathProvider;
use SwagImportExport\Components\Utils\CommandHelper;
use SwagImportExport\Components\Utils\SnippetsHelper;
use SwagImportExport\CustomModels\Profile;

class AutoImportService implements AutoImportServiceInterface
{
    private UploadPathProvider $uploadPathProvider;

    private ModelManager $modelManager;

    private ProfileFactory $profileFactory;

    private ?string $directory = null;

    public function __construct(
        UploadPathProvider $uploadPathProvider,
        ModelManager $modelManager,
        ProfileFactory $profileFactory
    ) {
        $this->uploadPathProvider = $uploadPathProvider;
        $this->modelManager = $modelManager;
        $this->profileFactory = $profileFactory;
    }

    public function runAutoImport(): void
    {
        $files = $this->getFiles();

        $lockerFilename = '__running';
        $lockerFileLocation = $this->getDirectory() . '/' . $lockerFilename;

        if (\in_array($lockerFilename, $files)) {
            $file = \fopen($lockerFileLocation, 'rb');
            $fileContent = (int) \fread($file, (int) \filesize($lockerFileLocation));
            \fclose($file);

            if ($fileContent > \time()) {
                echo 'There is already an import in progress.' . \PHP_EOL;

                return;
            }
            \unlink($lockerFileLocation);
        }

        if (\count($files) === 0) {
            echo 'No import files are found.' . \PHP_EOL;

            return;
        }

        $this->flagCronAsRunning($lockerFileLocation);

        $this->importFiles($files, $lockerFileLocation);

        \unlink($lockerFileLocation);
    }

    private function importFiles(array $files, string $lockerFileLocation): void
    {
        $profileRepository = $this->modelManager->getRepository(Profile::class);

        foreach ($files as $file) {
            $fileExtension = \strtolower(\pathinfo($file, \PATHINFO_EXTENSION));
            $fileName = \strtolower(\pathinfo($file, \PATHINFO_FILENAME));

            if ($fileExtension === 'xml' || $fileExtension === 'csv') {
                try {
                    $profile = $this->getProfile($fileName, $file, $profileRepository);

                    $mediaPath = $this->uploadPathProvider->getRealPath($file, UploadPathProvider::CRON_DIR);
                } catch (\Exception $e) {
                    echo $e->getMessage() . \PHP_EOL;
                    \unlink($lockerFileLocation);

                    return;
                }

                try {
                    $return = $this->start($profile, $mediaPath, $fileExtension);

                    $profilesMapper = ['articles', 'articlesImages'];

                    // loops the unprocessed data
                    $pathInfo = \pathinfo($mediaPath);
                    foreach ($profilesMapper as $profileName) {
                        $tmpFile = $this->uploadPathProvider->getRealPath(
                            $pathInfo['basename'] . '-' . $profileName . '-tmp.csv'
                        );

                        if (\file_exists($tmpFile)) {
                            $outputFile = \str_replace('-tmp', '-swag', $tmpFile);
                            \rename($tmpFile, $outputFile);

                            $profile = $this->profileFactory->loadHiddenProfile($profileName);
                            $profileEntity = $profile->getEntity();

                            $this->start($profileEntity, $outputFile, 'csv');
                        }
                    }

                    $message = $return['data']['position'] . ' ' . $return['data']['adapter'] . ' imported successfully' . \PHP_EOL;
                    echo $message;
                    \unlink($mediaPath);
                } catch (\Exception $e) {
                    // copy file as broken
                    $brokenFilePath = $this->uploadPathProvider->getRealPath(
                        'broken-' . $file,
                        UploadPathProvider::DIR
                    );
                    \copy($mediaPath, $brokenFilePath);

                    echo $e->getMessage() . \PHP_EOL;
                    \unlink($lockerFileLocation);

                    return;
                }
            }
        }
    }

    /**
     * @param ModelRepository<Profile> $profileRepository
     */
    private function getProfile(string $fileName, string $file, ModelRepository $profileRepository): Profile
    {
        $profile = CommandHelper::findProfileByName($file, $profileRepository);

        if (!$profile instanceof Profile) {
            $message = SnippetsHelper::getNamespace()->get('cronjob/no_profile', 'No profile found %s');

            throw new \Exception(\sprintf($message, $fileName));
        }

        return $profile;
    }

    /**
     * @return array<string>
     */
    private function getFiles(): array
    {
        $directory = $this->getDirectory();

        $allFiles = \scandir($directory);

        return \array_diff($allFiles, ['.', '..', '.htaccess']);
    }

    /**
     * Create empty file to flag cron as running
     */
    private function flagCronAsRunning(string $lockerFileLocation): void
    {
        $timeout = \time() + 1800;
        $file = \fopen($lockerFileLocation, 'wb');
        \fwrite($file, (string) $timeout);
        \fclose($file);
    }

    private function getDirectory(): string
    {
        if (!$this->directory) {
            $this->directory = $this->uploadPathProvider->getPath(UploadPathProvider::CRON_DIR);
        }

        return $this->directory;
    }

    private function start(Profile $profileModel, string $inputFile, string $format): array
    {
        $commandHelper = new CommandHelper(
            [
                'profileEntity' => $profileModel,
                'filePath' => $inputFile,
                'format' => $format,
                'username' => 'Cron',
            ]
        );

        $return = $commandHelper->prepareImport();
        $count = $return['count'];

        do {
            $return = $commandHelper->importAction();
            $position = $return['data']['position'];
        } while ($position < $count);

        return $return;
    }
}
