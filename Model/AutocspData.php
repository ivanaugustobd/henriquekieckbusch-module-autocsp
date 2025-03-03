<?php
declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Model;

use Magento\Framework\Model\AbstractModel;

class AutocspData extends AbstractModel
{
    /**
     * AbstractModel constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\AutocspData::class);
    }
}
