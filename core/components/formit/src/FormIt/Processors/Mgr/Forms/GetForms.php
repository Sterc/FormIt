<?php
namespace Sterc\FormIt\Processors\Mgr\Forms;

use MODX\Revolution\Processors\Model\GetListProcessor;
use MODX\Revolution\modContext;
use Sterc\FormIt\Model\FormItForm;
use xPDO\Om\xPDOQuery;
use xPDO\Om\xPDOObject;

class GetForms extends GetListProcessor
{
    /**
     * @access public.
     * @var String.
     */
    public $classKey = FormItForm::class;

    /**
     * @access public.
     * @var Array.
     */
    public $languageTopics = ['formit:default'];

    /**
     * @access public.
     * @var String.
     */
    public $defaultSortField = 'form';

    /**
     * @access public.
     * @var String.
     */
    public $defaultSortDirection = 'DESC';

    /**
     * @access public.
     * @var String.
     */
    public $objectType = 'formit.form';

    /**
     * @access public.
     * @return Mixed.
     */
    public function initialize()
    {
        $this->setDefaultProperties([
            'limit' => 0
        ]);

        return parent::initialize();
    }

    /**
     * @access public.
     * @param xPDOQuery $criteria.
     * @return xPDOQuery.
     */
    public function prepareQueryBeforeCount(xPDOQuery $criteria)
    {
        $criteria->where([
            'context_key:IN' => $this->getAvailableContexts()
        ]);

        $criteria->groupby('form');

        return $criteria;
    }

    /**
     * @access public.
     * @param xPDOObject $object.
     * @return Array.
     */
    public function prepareRow(xPDOObject $object)
    {
        return $object->toArray();
    }

    /**
     * @access public.
     * @return Array.
     */
    public function getAvailableContexts()
    {
        $contexts = ['-'];

        foreach ($this->modx->getCollection(modContext::class) as $context) {
            $contexts[] = $context->get('key');
        }

        return $contexts;
    }
}
