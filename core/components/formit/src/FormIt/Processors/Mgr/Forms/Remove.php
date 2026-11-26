<?php
namespace Sterc\FormIt\Processors\Mgr\Forms;

use MODX\Revolution\Processors\Model\RemoveProcessor;
use Sterc\FormIt\Model\FormItForm;

class Remove extends RemoveProcessor
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
}
