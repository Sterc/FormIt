<?php

namespace Sterc\FormIt\Hook;

use Sterc\FormIt\Service\RecaptchaService;

class Recaptcha
{
    /**
     * A reference to the hook instance.
     * @var \Sterc\FormIt\Hook\ $hook
     */
    public $hook;

    /**
     * A reference to the modX instance.
     * @var \modx $modx
     */
    public $modx;

    /**
     * An array of configuration properties
     * @var array $config
     */
    public $config = [];

    /**
     * A reference to the FormIt instance.
     * @var \Sterc\FormIt $formit
     */
    public $formit;

    /**
     * @param \Sterc\FormIt\Hook $hook
     * @param array $config
     */
    public function __construct($hook, array $config = array())
    {
        $this->hook =& $hook;
        $this->formit =& $hook->formit;
        $this->modx = $hook->formit->modx;
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Adds in reCaptcha support to FormIt
     *
     *
     * @return bool True if recaptcha has passed
     */
    public function process()
    {
        /** @var RecaptchaService $reCaptcha */
        $reCaptcha = $this->formit->request->loadReCaptcha();
        if (empty($reCaptcha->config[RecaptchaService::OPT_SECRET_KEY])) {
            $this->hook->addError('recaptcha', $this->modx->lexicon('recaptcha.no_api_key'));
            return false;
        }

        $token  = $_POST['g-recaptcha-response'] ?? '';
        $passed = $reCaptcha->verify($token);

        if (!$passed) {
            $this->hook->addError('recaptcha', $this->modx->lexicon('recaptcha.incorrect'));
        }

        return $passed;
    }
}
