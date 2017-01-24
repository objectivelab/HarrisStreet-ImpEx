<?php

namespace HarrisStreet\CoreConfigData\Exporter;

use HarrisStreet\CoreConfigData\AbstractImpexFileExtension;

/**
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @copyright   2014-present Zookal Pty Ltd, Sydney, Australia
 * @author      Cyrill at Schumacher dot fm [@SchumacherFM]
 */
abstract class AbstractExporter extends AbstractImpexFileExtension implements ExporterInterface
{
    private $_isHierarchical = FALSE;

    /**
     * @var \IteratorAggregate
     */
    protected $_collection = NULL;

    /**
     * Run script
     *
     */
    public function setData(\IteratorAggregate $collection)
    {
        $this->_collection = $collection;
        return $this;
    }

    /**
     * @param $str
     *
     * @return string
     */
    protected function _multiLineToSingleLine($str)
    {
        $str = str_replace(array("\r\n", "\n"), '\\n', $str);
        return addcslashes($str, '"');
    }

    /**
     * @param bool $isHierarchical
     *
     * @return $this
     */
    public function setIsHierarchical($isHierarchical)
    {
        $this->_isHierarchical = (boolean)$isHierarchical;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getIsHierarchical()
    {
        return $this->_isHierarchical;
    }

    /**
     * @return mixed
     */
    protected function _prepareCollection()
    {
        $prepare = TRUE === $this->getIsHierarchical() ? '_prepareHierarchicalCollection' : '_prepareFlatCollection';
        return $this->$prepare();
    }

    /**
     * Hmmmmmm
     *
     * @return array
     * @throws \Exception
     */
    protected function _prepareHierarchicalCollection()
    {
        // Magento 2
        if ($this->_collection instanceof \ArrayObject) {
            $collection = $this->_collection->getArrayCopy(); //convert to array

            //get rid of first level with numeric indexes
            $return = [];
            foreach ($collection as $index => $item) {
                $return += $item;
            }

            return $return;
        }

        // default Magento 1 behaviour
        $return = array();
        foreach ($this->_collection as $row) {
            /** @var $row \Mage_Core_Model_Config_Data */

            $pathDetails = explode('/', $row->getPath());

            if (!isset($return[$pathDetails[0]])) {
                $return[$pathDetails[0]] = array();
            }
            if (!isset($return[$pathDetails[0]][$pathDetails[1]])) {
                $return[$pathDetails[0]][$pathDetails[1]] = array();
            }
            if (!isset($return[$pathDetails[0]][$pathDetails[1]][$pathDetails[2]])) {
                $return[$pathDetails[0]][$pathDetails[1]][$pathDetails[2]] = array();
            }
            if (!isset($return[$pathDetails[0]][$pathDetails[1]][$pathDetails[2]][$row->getScope()])) {
                $return[$pathDetails[0]][$pathDetails[1]][$pathDetails[2]][$row->getScope()] = array();
            }

            $return[$pathDetails[0]][$pathDetails[1]][$pathDetails[2]][$row->getScope()]['' . $row->getScopeId()] = $row->getValue();
        }

        return $return;
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function _prepareFlatCollection()
    {
        $return = array();
        foreach ($this->_collection as $row) {
            /** @var $row \Mage_Core_Model_Config_Data */

            if (!isset($return[$row->getPath()])) {
                $return[$row->getPath()] = array();
            }
            if (!isset($return[$row->getPath()][$row->getScope()])) {
                $return[$row->getPath()][$row->getScope()] = array();
            }

            $return[$row->getPath()][$row->getScope()]['' . $row->getScopeId()] = $row->getValue();
        }

        return $return;
    }
}