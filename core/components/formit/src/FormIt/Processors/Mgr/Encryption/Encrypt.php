<?php

namespace Sterc\Formit\Processors\Mgr\Encryption;

use MODX\Revolution\Processors\Processor;
use Sterc\FormIt\Model\FormItForm;
use MODX\Revolution\modContext;

class Encrypt extends Processor
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
    public function process()
    {
        $amount = 0;

        $criteria = [
            'form'              => $this->getProperty('form'),
            'context_key:IN'    => $this->getAvailableContexts(),
            'encrypted'         => 0
        ];

        foreach ($this->modx->getCollection($this->classKey, $criteria) as $object) {
            $object->set('values', $object->encrypt($object->get('values')));
            $object->set('encrypted', 1);
            $object->set('encryption_type', 2);

            if ($object->save()) {
                $amount++;
            }
        }

        return $this->success($this->modx->lexicon('formit.encrypt_success', ['amount' => $amount]));
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
