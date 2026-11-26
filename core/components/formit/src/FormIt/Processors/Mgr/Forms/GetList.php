<?php

namespace Sterc\FormIt\Processors\Mgr\Forms;

use Sterc\FormIt\Model\FormItForm;
use MODX\Revolution\modContext;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOQuery;
use xPDO\Om\xPDOObject;

class GetList extends GetListProcessor
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
    public $defaultSortField = 'date';

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
            'dateFormat' => $this->modx->getOption('manager_date_format') . ', ' . $this->modx->getOption('manager_time_format')
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

        $form = $this->getProperty('form');

        if (!empty($form)) {
            $criteria->where([
                'form' => $form
            ]);
        }

        $context = $this->getProperty('context');

        if (!empty($context)) {
            $criteria->where([
                'context_key' => $context
            ]);
        }

        $query = $this->getProperty('query');

        if (!empty($query)) {
            $criteria->where([
                'form:LIKE'     => '%' . $query . '%',
                'OR:ip:LIKE'    => '%' . $query . '%'
            ]);
        }

        $startDate = $this->getProperty('start_date');

        if (!empty($startDate)) {
            $criteria->where([
                'date:>=' => strtotime(date('Y-m-d', strtotime($startDate)) . ' 00:00:00')
            ]);
        }

        $endDate = $this->getProperty('end_date');

        if (!empty($endDate)) {
            $criteria->where([
                'date:<=' => strtotime(date('Y-m-d', strtotime($endDate)) . ' 23:59:59')
            ]);
        }

        return $criteria;
    }

    /**
     * @access public.
     * @param xPDOObject $object.
     * @return Array.
     */
    public function prepareRow(xPDOObject $object)
    {
        $server_offset_time= floatval($this->modx->getOption('server_offset_time', null, 0)) * 3600;
        $array = array_merge($object->toArray(), [
            'date' => date($this->getProperty('dateFormat'), $object->get('date') + $server_offset_time)
        ]);

        $values = $object->get('values');

        if ((int) $object->get('encrypted') === 1) {
            $values = $object->decrypt($object->get('values'), $object->get('type'));
        }

        $values = json_decode($values, true);

        if ($values) {
            $array['values'] = $values;
        } else {
            $array['values'] = [];
        }

        return $array;
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
