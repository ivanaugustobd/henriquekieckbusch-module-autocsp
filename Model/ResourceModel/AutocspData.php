<?php
declare(strict_types=1);

namespace HenriqueKieckbusch\AutoCSP\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AutocspData extends AbstractDb
{
    /**
     * AutocspData constructor.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('autocsp_data', 'entity_id');
    }
}
