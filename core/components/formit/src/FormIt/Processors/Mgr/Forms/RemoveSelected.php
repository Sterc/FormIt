<?php

namespace Sterc\FormIt\Processors\Mgr\Forms;

use MODX\Revolution\Processors\Processor;
use Sterc\FormIt\Model\FormItForm;

/**
 * FormIt
 *
 * Copyright 2019 by Sterc <modx@sterc.nl>
 */

class RemoveSelected extends Processor
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
        $criteria = $this->modx->newQuery($this->classKey, [
            'id:IN' => explode(',', $this->getProperty('ids'))
        ]);

        foreach ($this->modx->getCollection($this->classKey, $criteria) as $object) {
            $object->remove();
        }

        return $this->success();
    }
}
