<?php
namespace Sterc\FormIt\Processors\Mgr\Forms;

use MODX\Revolution\Processors\Processor;
use MODX\Revolution\modContext;
use Sterc\FormIt\Model\FormItForm;

class Clean extends Processor
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
    public $objectType = 'formit.form';

    /**
     * @access public.
     * @return Mixed.
     */
    public function initialize()
    {
        return parent::initialize();
    }

    /**
     * @access public.
     * @return Mixed.
     */
    public function process()
    {
        $amount = 0;

        $criteria = [
            'context_key:IN' => $this->getAvailableContexts(),
            'date:<'         => strtotime('-' . $this->getProperty('days', $this->modx->getOption('formit.cleanform.days')) . ' days')
        ];

        foreach ($this->modx->getCollection($this->classKey, $criteria) as $object) {
            if ($object->remove()) {
                $amount++;
            }
        }

        return $this->success($this->modx->lexicon('formit.forms_clean_success', [
            'amount' => $amount
        ]));
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
