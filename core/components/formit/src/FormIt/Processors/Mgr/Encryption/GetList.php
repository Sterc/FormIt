<?php
namespace Sterc\FormIt\Processors\Mgr\Encryption;

use MODX\Revolution\Processors\Model\GetListProcessor;
use Sterc\FormIt\Model\FormItForm;
use MODX\Revolution\modContext;
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
    public $defaultSortField = 'form';

    /**
     * @access public.
     * @var String.
     */
    public $defaultSortDirection = 'ASC';

    /**
     * @access public.
     * @var String.
     */
    public $objectType = 'formit.form';

    /**
     * @access public.
     * @param xPDOQuery $criteria.
     * @return xPDOQuery.
     */
    public function prepareQueryBeforeCount(xPDOQuery $criteria)
    {
        $criteria->where([
            'context_key:IN' => $this->getAvailableContexts(),
        ]);

        $query = $this->getProperty('query');
        if (!empty($query)) {
            $criteria->where([
                'form:LIKE' => '%' . $query . '%'
            ]);
        }

        $criteria->select('MIN(id) AS id, form, context_key');
        $criteria->groupby('form');
        $criteria->groupby('context_key');

        return $criteria;
    }

    /**
     * @access public.
     * @param xPDOObject $object.
     * @return Array.
     */
    public function prepareRow(xPDOObject $object)
    {
        return array_merge($object->toArray('', false, true), [
            'encrypted'     => $this->modx->getCount($this->classKey, [
                'form'          => $object->get('form'),
                'context_key'   => $object->get('context_key'),
                'encrypted'     => 1
            ]),
            'decrypted'     => $this->modx->getCount($this->classKey, [
                'form'          => $object->get('form'),
                'context_key'   => $object->get('context_key'),
                'encrypted'     => 0
            ])
        ]);
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
