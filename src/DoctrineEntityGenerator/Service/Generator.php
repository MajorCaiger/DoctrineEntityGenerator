<?php

namespace MajorCaiger\DoctrineEntityGenerator\Service;

use Zend\Console\Adapter\AdapterInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Application as DoctrineCli;

/**
 * Generator
 *
 * @author Rob Caiger <rob@clocal.co.uk>
 */
class Generator
{
    /**
     * @var AdapterInterface
     */
    private $console;

    /**
     * @var array
     */
    private $config;

    /**
     * @var DoctrineCli
     */
    private $doctrineCli;

    /**
     * @var array
     */
    private $mappingFiles = [];

    /**
     * @var array
     */
    private $entities = [];

    public function __construct(AdapterInterface $console, array $config, DoctrineCli $doctrineCli)
    {
        $this->console = $console;

        $this->config = $config;

        $this->doctrineCli = $doctrineCli;
    }

    public function generate()
    {
        $this->removeOldMappingFiles();

        $this->generateNewMappingFiles();

        $this->removeOldEntities();

        $this->findMappingFiles();

        $this->compileEntityConfig();
    }

    /**
     * Remove old mapping files
     */
    private function removeOldMappingFiles()
    {
        $this->console->write('Removing old mapping files...');

        foreach (new \DirectoryIterator($this->config['mapping_files']) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }

            $fileName = $fileInfo->getFilename();

            if (strstr($fileName, '.dcm.xml')) {
                unlink($this->config['mapping_files'] . $fileName);
                if (file_exists($this->config['mapping_files'] . $fileName)) {
                    throw new \Exception('Unable to remove mapping file');
                }
            }
        }

        $this->console->write('Old mapping files removed');
    }

    /**
     * Generate new mapping files
     */
    private function generateNewMappingFiles()
    {
        $this->console->write('Generating new mapping files...');

        // Setup the custom mapping object
        $command = new ConvertMappingCommand($this->config['mapping_config']);

        // Prevent auto exit
        $this->doctrineCli->setAutoExit(false);
        // Add the custom command
        $this->doctrineCli->add($command);

        // Mock the cli input
        $argv = new ArgvInput(
            [
                'blah',
                'orm:convert-mapping',
                '--namespace=' . $this->config['namespace'],
                '--force',
                '--from-database',
                'xml',
                $this->config['mapping_files']
            ]
        );

        // Create the output object
        $output = new BufferedOutput();

        // Run the command
        $this->doctrineCli->run($argv, $output);
        $content = $output->fetch();

        if (!empty($content)) {
            $this->console->write($content);
            throw new \Exception('Error generating mapping files');
        }

        $this->console->write('Generated new files');
    }

    /**
     * Remove old entities
     */
    private function removeOldEntities()
    {
        $this->console->write('Removing old entities...');

        $entityDirectory = $this->config['directory'];

        $error = false;

        $di = new \RecursiveDirectoryIterator($entityDirectory, \FilesystemIterator::SKIP_DOTS);
        $ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $file) {
            if ($file->isFile()) {
                $fileName = $file->getFilename();
                $filePath = $file->getPath() . '/' . $fileName;

                if (preg_match('/Abstract([^.]+).php/', $fileName)) {
                    unlink($filePath);
                    if (file_exists($filePath)) {
                        throw new \Exception('Unable to remove old entities');
                    }
                }
            }
        }

        $this->console->write('Old entities were removed');
    }

    /**
     * Find all mapping files
     */
    private function findMappingFiles()
    {
        foreach (new \DirectoryIterator($this->config['mapping_files']) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }

            $fileName = $fileInfo->getFilename();

            $key = '\\' . str_replace('.', '\\', str_replace('.dcm.xml', '', $fileName));

            $this->mappingFiles[$key] = $fileName;
        }

        // @Note Sort the mapping files by key, as the OS may read them in a different order which will cause
        //   unnecessary changes
        ksort($this->mappingFiles, SORT_NATURAL);
    }

    /**
     * Compile entity configs
     */
    private function compileEntityConfig()
    {
        $this->console->write('Compiling entity configuration...');

        foreach ($this->mappingFiles as $className => $fileName) {

            $config = $this->getConfigFromMappingFile($fileName);

            die('here');
            $defaults = $this->getDefaultsFromTable($config['entity']['@attributes']['table']);

            $nullables = $this->getNullablesFromTable($config['entity']['@attributes']['table']);

            $camelCaseName = str_replace(self::ENTITY_NAMESPACE, '', $config['entity']['@attributes']['name']);

            $namespace = $this->findNamespace($camelCaseName);
            $relativeNamespace = $this->findRelativeNamespace($camelCaseName);

            $fields = $this->getFieldsFromConfig($config, $defaults, $comments, $camelCaseName, $nullables);

            $this->cacheFields($fields);

            $this->entities[$camelCaseName] = array(
                'name' => $camelCaseName,
                'softDeletable' => $this->hasSoftDeleteField($fields),
                'translatable' => $this->hasTranslatableField($fields),
                'className' => $namespace . '\\' . $camelCaseName,
                'table' => $config['entity']['@attributes']['table'],
                'ids' => $this->getIdsFromFields($fields),
                'indexes' => $this->getIndexesFromConfig($config),
                'unique-constraints' => $this->getUniqueConstraintsFromConfig($config),
                'fields' => $fields,
                'hasCollections' => $this->getHasCollectionsFromConfig($config),
                'collections' => $this->getCollectionsFromConfig($config),
                'mappingFileName' => $fileName,
                'entityFileName' => $this->formatEntityFileName($className, $relativeNamespace),
                'entityConcreteFileName' => $this->formatEntityConcreteFileName($className, $relativeNamespace),
                'testFileName' => $this->formatUnitTestFileName($className, $relativeNamespace),
                'namespace' => $namespace,
                'hasCreatedOn' => $this->hasCreatedOn($fields),
                'hasModifiedOn' => $this->hasModifiedOn($fields)
            );

            if (isset($comments['@settings']['repository'])) {
                $this->entities[$camelCaseName]['repository'] = $comments['@settings']['repository'];
            }
        }

        ksort($this->entities, SORT_NATURAL);

        foreach ($this->entities as $className => &$details) {
            if (!isset($this->inverseFields[$className])) {
                continue;
            }

            foreach ($this->inverseFields[$className] as $fieldDetails) {

                $property = $fieldDetails['relationship'] == 'oneToMany' || $fieldDetails['relationship'] == 'oneToOne'
                    ? 'mapped-by' : 'inversed-by';

                $item = array(
                    '@attributes' => array(
                        'field' => $fieldDetails['property'],
                        'target-entity' => $this->replaceNamespace($fieldDetails['targetEntity']),
                        $property => $fieldDetails['inversedBy']
                    ),
                    'orderBy' => $fieldDetails['orderBy']
                );

                if (isset($fieldDetails['cascade'])) {
                    $item['@attributes']['cascade'] = $fieldDetails['cascade'];
                }

                if (isset($fieldDetails['fetch'])) {
                    $item['@attributes']['fetch'] = $fieldDetails['fetch'];
                }

                $details['fields'][] = array(
                    'isId' => false,
                    'isInverse' => true,
                    'type' => $fieldDetails['relationship'],
                    'ref' => 'field',
                    'default' => null,
                    'config' => $item,
                    'isVersion' => false
                );

                if ($fieldDetails['relationship'] == 'manyToMany' || $fieldDetails['relationship'] == 'oneToMany') {
                    $details['hasCollections'] = true;

                    $details['collections'][] = $item;
                }
            }
        }

        $this->respond('Entity configurations compiled', 'success');
    }

    /**
     * Get the config from the mapping file
     *
     * @param string $fileName
     */
    private function getConfigFromMappingFile($fileName)
    {
        $xml = file_get_contents($this->config['mapping_files'] . $fileName);

        $result = json_decode(json_encode(simplexml_load_string($xml)), true);

        $config = $result;

        return $config;
    }
}
