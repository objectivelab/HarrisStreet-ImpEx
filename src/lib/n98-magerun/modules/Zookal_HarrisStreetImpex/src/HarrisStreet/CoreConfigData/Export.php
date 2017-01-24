<?php
/**
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @copyright   2014-present Zookal Pty Ltd, Sydney, Australia
 * @author      Cyrill at Schumacher dot fm [@SchumacherFM]
 */

namespace HarrisStreet\CoreConfigData;

use HarrisStreet\CoreConfigData\Exporter\ExporterInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

class Export extends AbstractImpex
{
    /**
     * @var ExporterInterface
     */
    protected $_exporterInstance = NULL;

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('hs:ccd:export')
            ->addOption('filename', 'f', InputOption::VALUE_OPTIONAL, 'File name into which should the export be written. Defaults into var directory.')
            ->addOption('include', 'i', InputOption::VALUE_OPTIONAL, 'Path prefix, multiple values can be comma separated; exports only those paths')
            ->addOption('includeScope', null, InputOption::VALUE_OPTIONAL, 'Scope name, multiple values can be comma separated; exports only those scopes')
            ->addOption('exclude', 'x', InputOption::VALUE_OPTIONAL, 'Path prefix, multiple values can be comma separated; exports everything except ...')
            ->addOption('filePerNameSpace', 's', InputOption::VALUE_OPTIONAL,
                'Export each namespace into its own file. Enable with: y', 'n')
            ->addOption('exclude-default', 'c', InputOption::VALUE_OPTIONAL, 'Excludes default values (@todo)')
            ->setDescription('HarrisStreet: Exports Core_Config_Data settings into a file.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->_exporterInstance = $this->_getFormatClass();

        if (FALSE === $this->_exporterInstance) {
            throw new \InvalidArgumentException('No supported export format found!');
        }

        $this->_exporterInstance->setIsHierarchical('y' === $input->getOption('hierarchical'));
        if ('y' === $input->getOption('filePerNameSpace')) {
            return $this->_createMultipleFiles();
        }

        return $this->_createSingleFile();
    }

    /**
     * Exports one file per namespace
     *
     * @return int
     */
    protected function _createMultipleFiles()
    {
        $collection = $this->_getExportCollection();

        $subCollections = array();
        foreach ($collection as $item) {
            /** @var $item \Mage_Core_Model_Config_Data */
            $nameSpaceParts = explode('/', $item->getPath());
            $nameSpace      = $nameSpaceParts[0];
            if (FALSE === isset($subCollections[$nameSpace])) {
                $subCollections[$nameSpace] = new \Varien_Data_Collection();
            }
            $subCollections[$nameSpace]->addItem($item);
        }

        foreach ($subCollections as $nameSpace => $nameSpaceCollection) {
            $this->_exporterInstance->setData($nameSpaceCollection);
            $this->_writeFile($this->_getMultipleFilename($nameSpace), $nameSpaceCollection->count());
        }

        return 0;
    }

    /**
     * Exports everything in one file
     *
     * @return int
     */
    protected function _createSingleFile()
    {
        $collection = $this->_getExportCollection();
        $this->_exporterInstance->setData($collection);

        return $this->_writeFile($this->_getFileName(), $collection->count());
    }

    /**
     * @param string $fileName
     * @param int    $count
     *
     * @return int
     */
    protected function _writeFile($fileName, $count)
    {
        $written = file_put_contents($fileName, $this->_exporterInstance->getData());
        if (FALSE === $written) {
            $this->_output->writeln('<error>Failed to write: ' . $fileName . '</error>');
            return 1;
        }
        $this->_output->writeln('<info>Wrote: ' . $count . ' settings to file ' . $fileName . '</info>');
        return 0;
    }

    /**
     * @return string
     */
    protected function _getFileName()
    {
        $fileName = $this->_input->getOption('filename');
        if (FALSE === empty($fileName)) {
            return $fileName . '.' . $this->_exporterInstance->getFileNameExtension();
        }

        if (isset($this->_magentoRootFolder)) {
            $configRoot = $this->_magentoRootFolder . '/var';
        } else {
            $configRoot =  \Mage::getBaseDir('var');
        }
        return $configRoot . DIRECTORY_SEPARATOR . 'config_' . date('Ymd_His') . '.' . $this->_exporterInstance->getFileNameExtension();
    }

    /**
     * @param string $nameSpace
     *
     * @return string
     */
    protected function _getMultipleFilename($nameSpace)
    {
        $fileName = $this->_getFileName($nameSpace);
        $fnParts  = explode('.', $fileName);
        $ext      = array_pop($fnParts);
        return implode('.', $fnParts) . '_' . $nameSpace . '.' . $ext;
    }
}