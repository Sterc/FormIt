<?php

/**
 * FormIt
 *
 * Copyright 2019 by Sterc <modx@sterc.nl>
 */

require_once dirname(__DIR__) . '/index.class.php';

class FormItHomeManagerController extends FormItBaseManagerController
{
    public function initialize()
    {
        if (isset($_GET['formid']) && isset($_GET['file'])) {
            $form = $this->modx->getObject(\Sterc\FormIt\Model\FormItForm::class, $_GET['formid']);
            if ($form) {
                $form->downloadFile($_GET['file']);
                exit;
            }
        }
        return parent::initialize();
    }
    /**
     * @access public.
     */
    public function loadCustomCssJs()
    {
        $this->addJavascript($this->modx->formit->config['js_url'] . 'mgr/widgets/home.panel.js');
        $this->addJavascript($this->modx->formit->config['js_url'] . 'mgr/widgets/forms.grid.js');
        $this->addJavascript($this->modx->formit->config['js_url'] . 'mgr/widgets/encryption.grid.js');
        $this->addLastJavascript($this->modx->formit->config['js_url'] . 'mgr/sections/home.js');
    }

    /**
     * @access public.
     * @return String.
     */
    public function getPageTitle()
    {
        return $this->modx->lexicon('formit');
    }

    /**
     * @access public.
     * @return String.
     */
    public function getTemplateFile()
    {
        return $this->modx->formit->config['templates_path'] . 'home.tpl';
    }
}