<?php
/**
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @copyright   2014-present Zookal Pty Ltd, Sydney, Australia
 * @author      Cyrill at Schumacher dot fm [@SchumacherFM]
 */

namespace HarrisStreet\CoreConfigData;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractImpex extends AbstractMagentoCommand
{
    /**
     * @var InputInterface
     */
    protected $_input = null;

    /**
     * @var OutputInterface
     */
    protected $_output = null;

    /**
     * @var \Magento\Framework\App\ObjectManager
     */
    protected $_objectManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    public function __construct()
    {
        parent::__construct();

        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_scopeConfig = $this->_objectManager->create('Magento\Framework\App\Config\ScopeConfigInterface');
    }

    protected function configure()
    {
        $this
            ->setName('hs:ccd:xxx')
            ->addOption('format', 'm', InputOption::VALUE_OPTIONAL, 'Format: yaml, json, csv, xml, limeSodaXml', 'yaml')
            ->addOption('hierarchical', 'a', InputOption::VALUE_OPTIONAL,
                'Create a hierarchical or a flat structure (not all export format supports that). Enable with: y', 'n');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_input  = $input;
        $this->_output = $output;
        $this->detectMagento($this->_output, true);
        if (false === $this->initMagento()) {
            throw new \RuntimeException('Magento could not be loaded');
        }
    }

    /**
     * @param string $type
     *
     * @return object
     */
    protected function _getFormatClass($type = 'Exporter')
    {
        $format      = $this->_input->getOption('format');
        $class       = ucfirst($format);
        $classPrefix = 'HarrisStreet\\CoreConfigData\\' . $type . '\\';

        if (true === class_exists($classPrefix . $class, true)) {
            $interfaces = class_implements($classPrefix . $class);
            if (isset($interfaces[$classPrefix . $type . 'Interface'])) {
                $c = $classPrefix . $class;
                return new $c();
            }
        }
        return false;
    }

    /**
     * Get config variable value(s) by path
     * Example:
     *     $this->getConfig('shipping/origin/city');
     *     $this->getConfig('shipping');
     *
     * @param string $configPath
     * @param int $scopeCode - store ID or default scope
     * @return mixed
     */
    protected function getConfig($configPath, $scopeCode = 0)
    {
        return $this->_scopeConfig->getValue(
            $configPath,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    /**
     * @return \Varien_Data_Collection
     */
    protected function _getExportCollection()
    {
        if (defined('self::MAGENTO_MAJOR_VERSION_2') && self::MAGENTO_MAJOR_VERSION_2 === 2) {
            return $this->_getMagento2ExportCollection();
        } else {
            return $this->_getMagento1ExportCollection();
        }
    }

    /**
     * @return \ArrayObject
     */
    protected function _getMagento2ExportCollection()
    {
        $scope = $this->_input->getOption('includeScope', 0); //TODO: support multiple scopes
        $includes = ['general', 'catalog', 'sales', 'carriers', 'design', 'currency', 'crontab',
            'cataloginventory', 'checkout', 'shipping', 'admin', 'dev', 'web'];

        if ($includedPaths = $this->_input->getOption('include')) {
            $includes = array_merge(explode(',', $includedPaths), $includes);
        }
        sort($includes);

        $collection = new \ArrayObject();
        if (count($includes)) {
            foreach ($includes as $path) {
                $values = $this->getConfig($path, $scope);
                asort($values);

                $currentPath = array($path => $values);
                $collection->append($currentPath);
            }
        }

        return $collection;
    }

    /**
     * @return \Varien_Data_Collection
     */
    protected function _getMagento1ExportCollection()
    {
        /** @var \Mage_Core_Model_Resource_Config_Data_Collection $collection */
        $collection = \Mage::getModel('core/config_data')->getCollection();
        $collection->setOrder('path', 'asc')->setOrder('scope', 'asc')->setOrder('scope_id', 'asc');

        $include = $this->_input->getOption('include');
        if (!empty($include) && is_string($include) === true) {
            $includes = explode(',', $include);
            $orWhere  = array();
            foreach ($includes as $singlePath) {
                $singlePath = trim($singlePath);
                if (!empty($singlePath)) {
                    $orWhere[] = $collection->getConnection()->quoteInto('`path` like ?', $singlePath . '%');
                }
            }
            if (count($orWhere) > 0) {
                $collection->getSelect()->where(implode(' or ', $orWhere));
            }
        }

        $includeScope = $this->_input->getOption('includeScope');
        if (!empty($includeScope) && is_string($includeScope) === true) {
            $includeScopes = explode(',', $includeScope);
            $orWhere       = array();
            foreach ($includeScopes as $singlePath) {
                $singlePath = trim($singlePath);
                if (!empty($singlePath)) {
                    $orWhere[] = $collection->getConnection()->quoteInto('`scope` like ?', $singlePath);
                }
            }
            if (count($orWhere) > 0) {
                $collection->getSelect()->where(implode(' or ', $orWhere));
            }
        }

        $exclude = $this->_input->getOption('exclude');
        if (!empty($exclude) && is_string($exclude) === true) {
            $excludes = explode(',', $exclude);
            $select   = $collection->getSelect();
            foreach ($excludes as $singleExclude) {
                $singleExclude = trim($singleExclude);
                if (!empty($singleExclude)) {
                    $select->Where('`path` not like ?', $singleExclude . '%');
                }
            }
        }
        // remove the id field and sort columns a-z
        foreach ($collection as $item) {
            /** @var $item \Mage_Core_Model_Config_Data */
            $data = $item->getData();
            unset($data['config_id']);
            ksort($data);
            $item->setData($data);
        }
        return $collection;
    }
}